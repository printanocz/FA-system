<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Domain\Mail\EmailSettings;
use App\Domain\Pdf\InvoicePdf;
use App\Mail\InvoiceMail;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\EmailLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Odešle fakturu (nebo upomínku) e-mailem s archivním PDF, synchronně.
 *
 * Úspěch: záznam v email_log, `sent_at` a stav Odesláno (z Vystaveno); upomínka navíc zvýší
 * `reminders_count` a nastaví `last_reminder_at`. Chyba odeslání: záznam v email_log se stavem
 * failed, doklad se nezmění a vyhodí se ValidationException s pochopitelnou zprávou (klíč `send`).
 */
final class SendInvoiceEmail
{
    public function __construct(
        private readonly InvoicePdf $pdf,
        private readonly EmailSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $input  type, to, cc[], copy_to_me, subject, body
     *
     * @throws ValidationException
     * @throws InvalidDocumentStateException
     */
    public function __invoke(Document $document, array $input): EmailLog
    {
        $data = $this->validate($input);
        $type = $data['type'];

        $this->assertSendable($document, $type);

        $company = CompanyProfile::current();
        $copyTo = $data['copy_to_me'] ? array_values(array_filter([$company?->email])) : [];
        $cc = array_values(array_unique(array_map('strtolower', $data['cc'] ?? [])));
        $cc = array_values(array_diff($cc, [strtolower($data['to'])]));

        $log = new EmailLog([
            'document_id' => $document->id,
            'type' => $type,
            'to_address' => $data['to'],
            'cc' => $cc === [] ? null : $cc,
            'bcc' => $copyTo === [] ? null : $copyTo,
            'subject' => $data['subject'],
        ]);

        try {
            $mail = new InvoiceMail(
                mailSubject: $data['subject'],
                body: $data['body'],
                senderName: $this->settings->senderName($company?->name),
                replyToAddress: $company?->email ?: null,
                cc: $cc,
                bcc: $copyTo,
                pdf: $this->pdf->contents($document),
                pdfName: $this->pdf->filename($document),
            );

            Mail::to($data['to'])->send($mail);
        } catch (Throwable $e) {
            $log->fill(['status' => EmailLog::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000)])->save();

            report($e);

            throw ValidationException::withMessages([
                'send' => 'E-mail se nepodařilo odeslat, nic se nezměnilo. Zkontrolujte nastavení pošty na serveru a zkuste to znovu. ('
                    .mb_substr($e->getMessage(), 0, 200).')',
            ]);
        }

        DB::transaction(function () use ($document, $log, $type): void {
            $now = Carbon::now();
            $log->fill(['status' => EmailLog::STATUS_SENT, 'sent_at' => $now])->save();

            $changes = [];
            if ($document->sent_at === null) {
                $changes['sent_at'] = $now;
            }
            if ($document->status === DocumentStatus::Issued) {
                $changes['status'] = DocumentStatus::Sent;
            }
            if ($type === EmailLog::TYPE_REMINDER) {
                $changes['reminders_count'] = $document->reminders_count + 1;
                $changes['last_reminder_at'] = $now;
            }
            if ($changes !== []) {
                $document->forceFill($changes)->save();
            }
        });

        return $log;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{type: string, to: string, cc: list<string>|null, copy_to_me: bool, subject: string, body: string}
     */
    private function validate(array $input): array
    {
        /** @var array{type: string, to: string, cc: list<string>|null, copy_to_me: bool, subject: string, body: string} */
        return Validator::make($input, [
            'type' => ['required', 'in:'.EmailLog::TYPE_INVOICE.','.EmailLog::TYPE_REMINDER],
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'array', 'max:10'],
            'cc.*' => ['email', 'max:255'],
            'copy_to_me' => ['required', 'boolean'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'to.required' => 'Vyplňte adresu příjemce.',
            'to.email' => 'Adresa příjemce není platná.',
            'cc.*.email' => 'Kopie: „:input“ není platná e-mailová adresa.',
            'subject.required' => 'Vyplňte předmět.',
            'body.required' => 'Vyplňte text e-mailu.',
        ])->validate();
    }

    private function assertSendable(Document $document, string $type): void
    {
        if (! $document->type->isPayable() || ! $document->isLocked()
            || in_array($document->status, [DocumentStatus::Draft, DocumentStatus::Cancelled], true)) {
            throw new InvalidDocumentStateException('E-mailem jde odeslat jen vystavená faktura.');
        }

        if ($type === EmailLog::TYPE_REMINDER) {
            $open = in_array($document->status, [DocumentStatus::Issued, DocumentStatus::Sent, DocumentStatus::PartiallyPaid], true);

            if (! $open || $document->computedStatus()?->value !== 'overdue') {
                throw new InvalidDocumentStateException('Upomínku lze poslat jen u faktury po splatnosti.');
            }
        }
    }
}
