<?php

namespace App\Domain\Mail;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Pdf\InvoicePdf;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Support\Carbon;

/**
 * Předvyplněný e-mail pro Sheet „Odeslat e-mailem“ (detail faktury i Přehled): příjemce a kopie
 * z kontaktu, předmět a text ze šablon v Nastavení s dosazenými proměnnými.
 */
final class EmailDraftBuilder
{
    public function __construct(
        private readonly EmailSettings $settings,
        private readonly InvoicePdf $pdf,
    ) {}

    /** @return array<string, mixed> */
    public function build(Document $invoice, ?Contact $contact, bool $overdue): array
    {
        $templates = $this->settings->all();
        $variables = EmailTemplate::variables($invoice);
        $status = $invoice->status;
        $sendable = $invoice->isLocked() && in_array($status, [
            DocumentStatus::Issued, DocumentStatus::Sent, DocumentStatus::PartiallyPaid, DocumentStatus::Paid,
        ], true);

        return [
            'can_send' => $sendable,
            'can_remind' => $overdue && in_array($status, [DocumentStatus::Issued, DocumentStatus::Sent, DocumentStatus::PartiallyPaid], true),
            'to' => $contact->email ?? '',
            'cc' => array_values($contact->email_cc ?? []),
            'copy_to_me' => (bool) $templates['copy_to_me'],
            // Poslední upomínka z posledních 7 dní: Sheet na ni upozorní (jen informace).
            'last_reminder_at' => $invoice->last_reminder_at !== null && $invoice->last_reminder_at->gt(Carbon::now()->subDays(7))
                ? $invoice->last_reminder_at->toDateString()
                : null,
            'copy_address' => CompanyProfile::current()->email ?? '',
            'attachment' => $this->pdf->filename($invoice),
            'invoice' => [
                'subject' => EmailTemplate::render((string) $templates['invoice_subject'], $variables),
                'body' => EmailTemplate::render((string) $templates['invoice_body'], $variables),
            ],
            'reminder' => [
                'subject' => EmailTemplate::render((string) $templates['reminder_subject'], $variables),
                'body' => EmailTemplate::render((string) $templates['reminder_body'], $variables),
            ],
        ];
    }
}
