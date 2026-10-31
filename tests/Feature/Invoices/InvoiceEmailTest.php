<?php

use App\Domain\Documents\DocumentStatus;
use App\Domain\Mail\EmailSettings;
use App\Domain\Mail\EmailTemplate;
use App\Mail\InvoiceMail;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\EmailLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/** Vystavená faktura 24 500 Kč se splatností 26. 10. 2026 pro kontakt s e-mailem a kopiemi. */
function mailInvoice(): Document
{
    CompanyProfile::current() ?? CompanyProfile::factory()->create(['name' => 'Adam Fuják', 'email' => 'jsem@adamfujak.cz']);
    $contact = Contact::factory()->create([
        'name' => 'Pekárna Hrubý s.r.o.',
        'email' => 'objednavky@pekarna.example',
        'email_cc' => ['ucetni@pekarna.example'],
    ]);

    return issueInvoice(
        draftInvoice([[1, 14500], [10, 600], [1, 4000]], ['contact_id' => $contact->id, 'issue_date' => '2026-10-12', 'due_date' => '2026-10-26']),
        '2026-10-12',
    );
}

/** @return array<string, mixed> */
function mailPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'invoice',
        'to' => 'objednavky@pekarna.example',
        'cc' => ['ucetni@pekarna.example'],
        'copy_to_me' => true,
        'subject' => 'Faktura 2026061',
        'body' => "Dobrý den,\n\nv příloze zasílám fakturu.\n\nAdam Fuják",
    ], $overrides);
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-25 09:00:00');
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

describe('sending an invoice', function () {
    it('sends the archive PDF with the copies and marks the invoice as sent', function () {
        Mail::fake();
        $invoice = mailInvoice();

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['subject' => 'Faktura '.$invoice->number]))
            ->assertRedirect()
            ->assertSessionHas('status', 'E-mail odeslán')
            ->assertSessionHasNoErrors();

        Mail::assertSent(InvoiceMail::class, 1);
        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
            foreach ($mail->attachments() as $attachment) {
                $attachment->attachTo($mail);
            }

            return $mail->hasTo('objednavky@pekarna.example')
                && $mail->hasCc('ucetni@pekarna.example')
                && $mail->hasBcc('jsem@adamfujak.cz')
                && $mail->hasReplyTo('jsem@adamfujak.cz')
                && $mail->hasFrom((string) config('mail.from.address'))
                && $mail->hasSubject('Faktura '.$invoice->number)
                && count($mail->rawAttachments) === 1
                && $mail->rawAttachments[0]['name'] === "Faktura-{$invoice->number}.pdf"
                && str_starts_with($mail->rawAttachments[0]['data'], '%PDF');
        });

        $invoice = $invoice->fresh();
        expect($invoice->status)->toBe(DocumentStatus::Sent)
            ->and($invoice->sent_at)->not->toBeNull()
            ->and($invoice->reminders_count)->toBe(0);

        $log = EmailLog::query()->sole();
        expect($log->document_id)->toBe($invoice->id)
            ->and($log->type)->toBe('invoice')
            ->and($log->status)->toBe('sent')
            ->and($log->to_address)->toBe('objednavky@pekarna.example')
            ->and($log->cc)->toBe(['ucetni@pekarna.example'])
            ->and($log->bcc)->toBe(['jsem@adamfujak.cz'])
            ->and($log->sent_at)->not->toBeNull();
    });

    it('sends the copy to me only when asked to', function () {
        Mail::fake();
        $invoice = mailInvoice();

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['copy_to_me' => false, 'cc' => []]));

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => ! $mail->hasBcc('jsem@adamfujak.cz') && ! $mail->hasCc('ucetni@pekarna.example'));
    });

    it('does not change the status of an already paid invoice', function () {
        Mail::fake();
        $invoice = mailInvoice();
        $invoice->forceFill(['status' => DocumentStatus::Paid])->save();

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload())->assertSessionHasNoErrors();

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Paid);
        Mail::assertSent(InvoiceMail::class, 1);
    });

    it('validates the recipient and the subject', function () {
        Mail::fake();
        $invoice = mailInvoice();

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['to' => 'neni-adresa', 'subject' => '', 'cc' => ['spatne']]))
            ->assertSessionHasErrors(['to', 'subject', 'cc.0']);

        Mail::assertNothingSent();
        expect(EmailLog::query()->count())->toBe(0);
    });

    it('refuses to send a draft', function () {
        Mail::fake();
        $draft = draftInvoice();

        $this->post("/faktury/{$draft->id}/odeslat", mailPayload())->assertSessionHas('error');

        Mail::assertNothingSent();
    });

    it('leaves everything unchanged and logs the error when the transport fails', function () {
        Mail::extend('boom', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection refused');
            }

            public function __toString(): string
            {
                return 'boom://';
            }
        });
        config(['mail.default' => 'boom', 'mail.mailers.boom' => ['transport' => 'boom']]);
        $invoice = mailInvoice();

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload())
            ->assertSessionHasErrors('send');

        expect(session('errors')->first('send'))->toContain('nepodařilo odeslat');

        $invoice = $invoice->fresh();
        expect($invoice->status)->toBe(DocumentStatus::Issued)
            ->and($invoice->sent_at)->toBeNull()
            ->and($invoice->reminders_count)->toBe(0);

        $log = EmailLog::query()->sole();
        expect($log->status)->toBe('failed')
            ->and($log->error)->toContain('Connection refused')
            ->and($log->sent_at)->toBeNull();
    });
});

describe('reminder', function () {
    it('is refused before the due date', function () {
        Mail::fake();
        $invoice = mailInvoice();

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['type' => 'reminder']))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        expect($invoice->fresh()->reminders_count)->toBe(0);
    });

    it('is sent after the due date and counts', function () {
        Mail::fake();
        $invoice = mailInvoice();
        Carbon::setTestNow('2026-10-31 10:00:00');

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['type' => 'reminder', 'subject' => 'Upomínka']))
            ->assertSessionHas('status', 'Upomínka odeslána');

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasSubject('Upomínka'));
        $invoice = $invoice->fresh();
        expect($invoice->reminders_count)->toBe(1)
            ->and($invoice->last_reminder_at?->toDateTimeString())->toBe('2026-10-31 10:00:00')
            ->and($invoice->status)->toBe(DocumentStatus::Sent);
        expect(EmailLog::query()->sole()->type)->toBe('reminder');
    });

    it('is refused for a paid invoice', function () {
        Mail::fake();
        $invoice = mailInvoice();
        $invoice->forceFill(['status' => DocumentStatus::Paid])->save();
        Carbon::setTestNow('2026-10-31 10:00:00');

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['type' => 'reminder']))->assertSessionHas('error');

        Mail::assertNothingSent();
    });
});

describe('template variables', function () {
    it('fills the variables from the invoice snapshots', function () {
        $invoice = mailInvoice();
        Carbon::setTestNow('2026-10-31 10:00:00');

        $variables = EmailTemplate::variables($invoice);

        expect($variables['cislo'])->toBe($invoice->number)
            ->and($variables['castka'])->toBe('24 500,00 Kč')
            ->and($variables['splatnost'])->toBe('26. 10. 2026')
            ->and($variables['vs'])->toBe($invoice->vs)
            ->and($variables['ucet'])->toBe('1234567890/0800')
            ->and($variables['odberatel'])->toBe('Pekárna Hrubý s.r.o.')
            ->and($variables['dodavatel'])->toBe('Adam Fuják')
            ->and($variables['dni_po_splatnosti'])->toBe('5');

        expect(EmailTemplate::render('Faktura {cislo}, {castka}, {neznama}', $variables))
            ->toBe("Faktura {$invoice->number}, 24 500,00 Kč, {neznama}");
    });

    it('prefills the sheet from the templates and the contact', function () {
        $invoice = mailInvoice();

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('email.can_send', true)
            ->where('email.can_remind', false)
            ->where('email.to', 'objednavky@pekarna.example')
            ->where('email.cc', ['ucetni@pekarna.example'])
            ->where('email.attachment', "Faktura-{$invoice->number}.pdf")
            ->where('email.invoice.subject', "Faktura {$invoice->number}")
            ->where('email.invoice.body', fn ($body) => str_contains((string) $body, 'Dobrý den,')
                && str_contains((string) $body, '24 500,00 Kč')
                && str_contains((string) $body, 'Adam Fuják')
                && ! str_contains((string) $body, '{'))
            ->where('emails', []));
    });

    it('offers the reminder only after the due date', function () {
        $invoice = mailInvoice();
        Carbon::setTestNow('2026-10-31 10:00:00');

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('email.can_remind', true)
            ->where('email.reminder.body', fn ($body) => str_contains((string) $body, '5 dní')));
    });

    it('lists the sent e-mails for the timeline', function () {
        Mail::fake();
        $invoice = mailInvoice();
        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload());

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->has('emails', 1)
            ->where('emails.0.type', 'invoice')
            ->where('emails.0.to', 'objednavky@pekarna.example'));
    });
});

describe('issue and send', function () {
    it('opens the detail with the send sheet after issuing', function () {
        CompanyProfile::factory()->create(['email' => 'jsem@adamfujak.cz']);
        $draft = draftInvoice();

        $this->post("/faktury/{$draft->id}/vystavit", ['send' => true])
            ->assertRedirect("/faktury/{$draft->id}?odeslat=1");

        expect($draft->fresh()->status)->toBe(DocumentStatus::Issued);
    });

    it('redirects to the plain detail without the send flag', function () {
        $draft = draftInvoice();

        $this->post("/faktury/{$draft->id}/vystavit")->assertRedirect("/faktury/{$draft->id}");
    });
});

describe('email settings', function () {
    it('has Czech default templates addressing the customer formally', function () {
        $values = app(EmailSettings::class)->all();

        expect($values['invoice_body'])->toContain('Vám')->toContain('{dodavatel}')
            ->and($values['reminder_subject'])->toContain('{cislo}')
            ->and($values['copy_to_me'])->toBeFalse();
    });

    it('saves templates together with the settings and uses them in the sheet', function () {
        $invoice = mailInvoice();

        $this->put('/nastaveni', [
            'company' => ['name' => 'Adam Fuják', 'ico' => '27082440', 'dic' => '', 'street' => 'Krásné Loučky 27/27', 'zip' => '794 01', 'city' => 'Krnov', 'registration_note' => '', 'vat_payer' => false, 'email' => 'jsem@adamfujak.cz', 'phone' => '', 'web' => ''],
            'documents' => [],
            'emails' => ['invoice_subject' => 'Vaše faktura {cislo}', 'invoice_body' => 'Dobrý den, {odberatel}', 'copy_to_me' => true, 'sender_name' => 'Adam F.'],
        ])->assertSessionHasNoErrors();

        $values = app(EmailSettings::class)->all();
        expect($values['invoice_subject'])->toBe('Vaše faktura {cislo}')
            ->and($values['copy_to_me'])->toBeTrue()
            ->and($values['sender_name'])->toBe('Adam F.');

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('email.invoice.subject', "Vaše faktura {$invoice->number}")
            ->where('email.invoice.body', 'Dobrý den, Pekárna Hrubý s.r.o.')
            ->where('email.copy_to_me', true));
    });

    it('rejects an empty subject', function () {
        $this->put('/nastaveni', [
            'company' => ['name' => 'Adam Fuják', 'ico' => '27082440', 'dic' => '', 'street' => 'Krásné Loučky 27/27', 'zip' => '794 01', 'city' => 'Krnov', 'registration_note' => '', 'vat_payer' => false, 'email' => 'jsem@adamfujak.cz', 'phone' => '', 'web' => ''],
            'documents' => [],
            'emails' => ['invoice_subject' => ''],
        ])->assertSessionHasErrors('emails.invoice_subject');
    });

    it('shows the templates and variable help on the settings page', function () {
        $this->get('/nastaveni')->assertInertia(fn (Assert $page) => $page
            ->where('emails.invoice_subject', 'Faktura {cislo}')
            ->has('emailVariables.dni_po_splatnosti'));
    });
});

describe('reminder double-send protection', function () {
    it('refuses a second reminder within five minutes and allows it later', function () {
        Mail::fake();
        $invoice = mailInvoice();
        Carbon::setTestNow('2026-10-31 10:00:00');

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['type' => 'reminder']))->assertSessionHas('status');
        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['type' => 'reminder']))->assertSessionHas('error');

        Mail::assertSent(InvoiceMail::class, 1);
        expect($invoice->fresh()->reminders_count)->toBe(1);

        Carbon::setTestNow('2026-10-31 10:06:00');
        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['type' => 'reminder']))->assertSessionHas('status');

        Mail::assertSent(InvoiceMail::class, 2);
        expect($invoice->fresh()->reminders_count)->toBe(2);
    });

    it('does not limit sending the invoice itself', function () {
        Mail::fake();
        $invoice = mailInvoice();

        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload())->assertSessionHas('status');
        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload())->assertSessionHas('status');

        Mail::assertSent(InvoiceMail::class, 2);
    });

    it('tells the sheet about a reminder sent within seven days only', function () {
        Mail::fake();
        $invoice = mailInvoice();
        Carbon::setTestNow('2026-10-29 10:00:00');
        $this->post("/faktury/{$invoice->id}/odeslat", mailPayload(['type' => 'reminder']));

        Carbon::setTestNow('2026-10-31 10:00:00');
        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page->where('email.last_reminder_at', '2026-10-29'));

        Carbon::setTestNow('2026-11-07 10:00:00');
        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page->where('email.last_reminder_at', null));
    });
});

describe('factories used by search tests', function () {
    it('create neutral data that cannot match the search terms of the tests', function () {
        $contact = Contact::factory()->create();

        foreach (['nov', 'nic', 'dtp', 'loga', 'pek', 'hrub', 'svob', 'fakturace', '270 82'] as $term) {
            expect(mb_strtolower($contact->name.' '.$contact->email))->not->toContain($term);
        }
        expect($contact->ico)->toMatch('/^9\d{7}$/')
            ->and(Contact::factory()->create()->email)->not->toBe($contact->email);
    });
});
