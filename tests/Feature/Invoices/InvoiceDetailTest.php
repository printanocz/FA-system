<?php

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentLockedException;
use App\Domain\Documents\PaymentMethod;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function detailInvoice(array $items = [[1, 24500]], string $issued = '2026-10-12', string $due = '2026-10-26', array $attributes = []): Document
{
    return issueInvoice(
        draftInvoice($items, array_merge(['issue_date' => $issued, 'due_date' => $due], $attributes)),
        $issued,
    );
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-25 09:00:00');
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

describe('only invoices are reachable under /faktury/{id}', function () {
    it('answers 404 for a quote on every invoice address', function () {
        CompanyProfile::current() ?? CompanyProfile::factory()->create();
        NumberSeries::factory()->forType(DocumentType::Quote)->create();
        $quote = Document::factory()->quote()->create();
        DocumentItem::factory()->create(['document_id' => $quote->id]);
        $quote = issueInvoice($quote, '2026-10-12');
        $id = $quote->id;

        $this->get("/faktury/{$id}")->assertNotFound();
        $this->get("/faktury/{$id}/upravit")->assertNotFound();
        $this->put("/faktury/{$id}", [])->assertNotFound();
        $this->post("/faktury/{$id}/vystavit", [])->assertNotFound();
        $this->delete("/faktury/{$id}")->assertNotFound();
        $this->post("/faktury/{$id}/kopie")->assertNotFound();
        $this->post("/faktury/{$id}/uhrady", ['amount' => '1', 'paid_at' => '2026-10-25'])->assertNotFound();

        expect(Document::query()->find($id))->not->toBeNull();
    });

    it('answers 404 for a proforma and a credit note too', function (DocumentType $type) {
        $document = Document::factory()->create(['type' => $type]);

        $this->get("/faktury/{$document->id}")->assertNotFound();
        $this->get("/faktury/{$document->id}/upravit")->assertNotFound();
    })->with([DocumentType::Proforma, DocumentType::CreditNote]);

    it('answers 404 for a missing invoice', function () {
        $this->get('/faktury/987654')->assertNotFound();
    });

    it('does not let a payment be removed through another invoice', function () {
        $first = detailInvoice();
        $second = detailInvoice();
        $payment = Payment::factory()->create(['document_id' => $first->id, 'amount' => '100.00']);

        $this->delete("/faktury/{$second->id}/uhrady/{$payment->id}")->assertNotFound();

        expect(Payment::query()->find($payment->id))->not->toBeNull();
    });
});

describe('detail', function () {
    it('redirects a draft to the editor', function () {
        $draft = draftInvoice();

        $this->get("/faktury/{$draft->id}")->assertRedirect("/faktury/{$draft->id}/upravit");
    });

    it('renders the detail of an issued invoice', function () {
        // Účet s platným IBANem, ať jde sestavit QR Platba (IBAN z továrny je jen vzorový).
        BankAccount::factory()->default()->create(['iban' => 'CZ6508000000192000145399']);
        $contact = Contact::factory()->create(['name' => 'Pekárna Hrubý s.r.o.', 'ico' => '08765432']);
        $invoice = detailInvoice([[1, 14500], [10, 600], [1, 4000]], attributes: ['contact_id' => $contact->id]);

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->where('invoice.number', $invoice->number)
            ->where('invoice.total', '24500.00')
            ->where('invoice.remaining', '24500.00')
            ->where('invoice.paid_percent', 0)
            ->where('invoice.computed_status', null)
            ->where('invoice.buyer.name', 'Pekárna Hrubý s.r.o.')
            ->where('invoice.show_taxable_date', false)
            ->has('invoice.items', 3)
            ->has('invoice.payments', 0)
            ->where('invoice.can_pay', true)
            ->where('invoice.can_delete', true)
            ->where('invoice.qr_svg', fn ($svg) => str_contains((string) $svg, '<svg'))
            ->where('contact.id', $contact->id)
            ->where('contact.stats.invoice_count', 1)
            ->where('contact.stats.turnover', '24500.00')
            ->where('related', []));
    });

    it('reports an overdue invoice with the days overdue', function () {
        $invoice = detailInvoice(due: '2026-10-14');

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('invoice.computed_status', 'overdue')
            ->where('invoice.overdue_days', 11));
    });

    it('shows the progress of payments and no QR code once everything is paid', function () {
        $invoice = detailInvoice([[1, 1000]]);
        $this->post("/faktury/{$invoice->id}/uhrady", ['amount' => '250', 'paid_at' => '2026-10-20']);

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('invoice.remaining', '750.00')
            ->where('invoice.paid_percent', 25)
            ->where('invoice.status', 'partially_paid')
            ->has('invoice.payments', 1)
            ->where('invoice.can_delete', false));

        $this->post("/faktury/{$invoice->id}/uhrady", ['amount' => '750', 'paid_at' => '2026-10-21']);

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('invoice.paid_percent', 100)
            ->where('invoice.status', 'paid')
            ->where('invoice.can_pay', false)
            ->where('invoice.qr_svg', null));
    });

    it('lists the related documents in both directions', function () {
        $invoice = detailInvoice();
        $other = detailInvoice(issued: '2026-10-13', due: '2026-10-27');
        $invoice->forceFill(['related_document_id' => $other->id])->save();

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('related.0.id', $other->id)
            ->where('related.0.url', url("/faktury/{$other->id}")));

        $this->get("/faktury/{$other->id}")->assertInertia(fn (Assert $page) => $page
            ->where('related.0.id', $invoice->id));
    });
});

describe('recording a payment', function () {
    beforeEach(fn () => $this->invoice = detailInvoice([[1, 3200]]));

    it('records a full payment typed the Czech way', function () {
        $this->from("/faktury/{$this->invoice->id}")
            ->post("/faktury/{$this->invoice->id}/uhrady", [
                'amount' => '3 200,00',
                'paid_at' => '2026-10-24',
                'method' => 'bank_transfer',
                'note' => 'Platba z účtu',
            ])
            ->assertRedirect("/faktury/{$this->invoice->id}")
            ->assertSessionHas('status', 'Úhrada zaznamenána')
            ->assertSessionHasNoErrors();

        $invoice = $this->invoice->fresh();
        $payment = $invoice->payments()->first();

        expect($invoice->status)->toBe(DocumentStatus::Paid)
            ->and($invoice->paid_total)->toBe('3200.00')
            ->and($invoice->paid_at->toDateString())->toBe('2026-10-24')
            ->and($payment->amount)->toBe('3200.00')
            ->and($payment->note)->toBe('Platba z účtu')
            ->and($payment->method)->toBe(PaymentMethod::BankTransfer);
    });

    it('records a partial payment and leaves the rest to pay', function () {
        $this->post("/faktury/{$this->invoice->id}/uhrady", ['amount' => '1200,50', 'paid_at' => '2026-10-24', 'method' => 'cash'])
            ->assertSessionHasNoErrors();

        $invoice = $this->invoice->fresh();

        expect($invoice->status)->toBe(DocumentStatus::PartiallyPaid)
            ->and($invoice->paid_total)->toBe('1200.50')
            ->and($invoice->paid_at)->toBeNull()
            ->and($invoice->remainingAmount()->__toString())->toBe('1999.50')
            ->and($invoice->payments()->first()->method)->toBe(PaymentMethod::Cash);
    });

    it('refuses an overpayment with an error on the amount field', function () {
        $this->post("/faktury/{$this->invoice->id}/uhrady", ['amount' => '3200,01', 'paid_at' => '2026-10-24'])
            ->assertSessionHasErrors(['amount' => "Částka přesahuje zbývající 3\u{00A0}200,00\u{00A0}Kč. Přeplatek není povolený."]);

        $invoice = $this->invoice->fresh();

        expect($invoice->payments()->count())->toBe(0)
            ->and($invoice->paid_total)->toBe('0.00')
            ->and($invoice->status)->toBe(DocumentStatus::Issued);
    });

    it('measures an overpayment against what is left after earlier payments', function () {
        $this->post("/faktury/{$this->invoice->id}/uhrady", ['amount' => '3000', 'paid_at' => '2026-10-20']);

        $this->post("/faktury/{$this->invoice->id}/uhrady", ['amount' => '300', 'paid_at' => '2026-10-24'])
            ->assertSessionHasErrors(['amount' => "Částka přesahuje zbývající 200,00\u{00A0}Kč. Přeplatek není povolený."]);
    });

    it('validates the fields in Czech', function (array $input, string $field, string $message) {
        $this->post("/faktury/{$this->invoice->id}/uhrady", $input)
            ->assertSessionHasErrors([$field => $message]);

        expect($this->invoice->fresh()->payments()->count())->toBe(0);
    })->with([
        'missing amount' => [['paid_at' => '2026-10-24'], 'amount', 'Zadejte částku úhrady.'],
        'text amount' => [['amount' => 'abc', 'paid_at' => '2026-10-24'], 'amount', 'Částka musí být číslo s nejvýše dvěma desetinnými místy, například 3 200,00.'],
        'three decimals' => [['amount' => '10,005', 'paid_at' => '2026-10-24'], 'amount', 'Částka musí být číslo s nejvýše dvěma desetinnými místy, například 3 200,00.'],
        'zero' => [['amount' => '0', 'paid_at' => '2026-10-24'], 'amount', 'Částka musí být větší než nula.'],
        'negative' => [['amount' => '-5', 'paid_at' => '2026-10-24'], 'amount', 'Částka musí být číslo s nejvýše dvěma desetinnými místy, například 3 200,00.'],
        'missing date' => [['amount' => '10'], 'paid_at', 'Zadejte datum úhrady.'],
        'bad date' => [['amount' => '10', 'paid_at' => '24. 10. 2026'], 'paid_at', 'Zadejte datum ve tvaru 25. 10. 2026.'],
        'future date' => [['amount' => '10', 'paid_at' => '2026-10-26'], 'paid_at', 'Datum úhrady nemůže být v budoucnu.'],
        'unknown method' => [['amount' => '10', 'paid_at' => '2026-10-24', 'method' => 'bitcoin'], 'method', 'Vyberte způsob úhrady.'],
    ]);

    it('refuses a payment on a draft with a message instead of an error page', function () {
        $draft = draftInvoice();

        $this->from("/faktury/{$draft->id}")
            ->post("/faktury/{$draft->id}/uhrady", ['amount' => '10', 'paid_at' => '2026-10-24'])
            ->assertSessionHas('error');

        expect($draft->payments()->count())->toBe(0);
    });
});

describe('deleting a payment', function () {
    beforeEach(function () {
        $this->invoice = detailInvoice([[1, 1000]]);
        $this->post("/faktury/{$this->invoice->id}/uhrady", ['amount' => '400', 'paid_at' => '2026-10-20']);
        $this->post("/faktury/{$this->invoice->id}/uhrady", ['amount' => '600', 'paid_at' => '2026-10-22']);
        [$this->older, $this->newer] = $this->invoice->payments()->get()->all();
    });

    it('removes the last payment and recalculates the state', function () {
        expect($this->invoice->fresh()->status)->toBe(DocumentStatus::Paid);

        $this->delete("/faktury/{$this->invoice->id}/uhrady/{$this->newer->id}")
            ->assertSessionHas('status', 'Úhrada smazána');

        $invoice = $this->invoice->fresh();

        expect($invoice->status)->toBe(DocumentStatus::PartiallyPaid)
            ->and($invoice->paid_total)->toBe('400.00')
            ->and($invoice->paid_at)->toBeNull()
            ->and($invoice->payments()->count())->toBe(1);
    });

    it('refuses to remove an older payment before the newer one', function () {
        $this->delete("/faktury/{$this->invoice->id}/uhrady/{$this->older->id}")
            ->assertSessionHas('error');

        expect($this->invoice->fresh()->payments()->count())->toBe(2)
            ->and($this->invoice->fresh()->status)->toBe(DocumentStatus::Paid);
    });

    it('returns the invoice to issued once the last payment is gone', function () {
        $this->delete("/faktury/{$this->invoice->id}/uhrady/{$this->newer->id}");
        $this->delete("/faktury/{$this->invoice->id}/uhrady/{$this->older->id}");

        $invoice = $this->invoice->fresh();

        expect($invoice->status)->toBe(DocumentStatus::Issued)
            ->and($invoice->paid_total)->toBe('0.00')
            ->and($invoice->payments()->count())->toBe(0);
    });

    it('marks only the last payment as deletable in the page props', function () {
        $this->get("/faktury/{$this->invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('invoice.payments.0.deletable', false)
            ->where('invoice.payments.1.deletable', true));
    });
});

describe('copying an invoice', function () {
    it('creates a draft with the same customer, items and texts and today\'s dates', function () {
        $contact = Contact::factory()->create(['default_due_days' => 21]);
        $source = detailInvoice(
            [[2, 800], [1, 300]],
            attributes: [
                'contact_id' => $contact->id,
                'text_before' => 'Fakturuji Vám:',
                'text_after' => 'Děkuji.',
                'note_internal' => 'Jen pro mě',
                'order_number' => 'OBJ-9',
                'show_taxable_date' => true,
            ],
        );
        $this->post("/faktury/{$source->id}/uhrady", ['amount' => '100', 'paid_at' => '2026-10-20']);
        DB::table('document_items')->where('document_id', $source->id)->where('position', 1)
            ->update(['name' => 'Úprava loga', 'description' => 'Dvě varianty', 'unit' => 'hod.']);

        $response = $this->post("/faktury/{$source->id}/kopie");

        $copy = Document::query()->latest('id')->first();
        $response->assertRedirect("/faktury/{$copy->id}/upravit")
            ->assertSessionHas('status', "Koncept vytvořen kopií faktury {$source->number}");

        expect($copy->id)->not->toBe($source->id)
            ->and($copy->status)->toBe(DocumentStatus::Draft)
            ->and($copy->isLocked())->toBeFalse()
            ->and($copy->number)->toBeNull()
            ->and($copy->contact_id)->toBe($contact->id)
            ->and($copy->issue_date->toDateString())->toBe('2026-10-25')
            ->and($copy->due_date->toDateString())->toBe('2026-11-15')
            ->and($copy->text_before)->toBe('Fakturuji Vám:')
            ->and($copy->text_after)->toBe('Děkuji.')
            ->and($copy->note_internal)->toBeNull()
            ->and($copy->order_number)->toBeNull()
            ->and($copy->show_taxable_date)->toBeTrue()
            ->and($copy->paid_total)->toBe('0.00')
            ->and($copy->payments()->count())->toBe(0)
            ->and($copy->total)->toBe('1900.00');

        $items = $copy->items()->get();
        expect($items)->toHaveCount(2)
            ->and($items[0]->name)->toBe('Úprava loga')
            ->and($items[0]->description)->toBe('Dvě varianty')
            ->and($items[0]->unit)->toBe('hod.')
            ->and($items[0]->qty)->toBe('2.0000')
            ->and($items[0]->unit_price)->toBe('800.00')
            ->and($items[0]->line_total)->toBe('1600.00')
            ->and($items[1]->line_total)->toBe('300.00');

        // Zdroj zůstal beze změny.
        expect($source->fresh()->items()->count())->toBe(2)
            ->and($source->fresh()->status)->toBe(DocumentStatus::PartiallyPaid);
    });

    it('copies a draft too and uses the default due days from the settings', function () {
        $draft = draftInvoice([[1, 500]], ['text_before' => 'Text']);

        $this->post("/faktury/{$draft->id}/kopie")->assertRedirect();

        $copy = Document::query()->latest('id')->first();

        expect($copy->id)->not->toBe($draft->id)
            ->and($copy->text_before)->toBe('Text')
            ->and($copy->due_date->toDateString())->toBe('2026-11-08')
            ->and($copy->items()->count())->toBe(1);
    });
});

describe('showing the taxable date', function () {
    it('stores the choice from the editor, off by default', function () {
        $contact = Contact::factory()->create();
        $payload = [
            'contact_id' => $contact->id,
            'issue_date' => '2026-10-25',
            'due_date' => '2026-11-08',
            'payment_method' => 'bank_transfer',
            'discount_percent' => '0',
            'round_total' => false,
            'items' => [['name' => 'Práce', 'qty' => '1', 'unit' => 'ks', 'unit_price' => '100']],
        ];

        $this->post('/faktury', $payload);
        $off = Document::query()->latest('id')->first();

        $this->post('/faktury', $payload + ['show_taxable_date' => true, 'taxable_date' => '2026-10-20']);
        $on = Document::query()->latest('id')->first();

        expect($off->show_taxable_date)->toBeFalse()
            ->and($off->taxable_date)->toBeNull()
            ->and($on->show_taxable_date)->toBeTrue()
            ->and($on->taxable_date->toDateString())->toBe('2026-10-20');

        $this->get("/faktury/{$on->id}/upravit")->assertInertia(fn (Assert $page) => $page
            ->where('form.show_taxable_date', true)
            ->where('form.taxable_date', '2026-10-20'));
    });

    it('still fills the taxable date on issuing when it is not shown', function () {
        $invoice = detailInvoice();

        expect($invoice->show_taxable_date)->toBeFalse()
            ->and($invoice->taxable_date->toDateString())->toBe('2026-10-12');

        $this->get("/faktury/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('invoice.show_taxable_date', false)
            ->where('invoice.taxable_date', '2026-10-12'));
    });

    it('cannot be changed after issuing', function () {
        $invoice = detailInvoice();

        $invoice->show_taxable_date = true;
        $invoice->save();
    })->throws(DocumentLockedException::class);
});
