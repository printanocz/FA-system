<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Documents\DuplicateInvoice;
use App\Actions\Documents\IssueDocument;
use App\Actions\Documents\SaveInvoiceDraft;
use App\Domain\Contacts\ContactStatistics;
use App\Domain\Contacts\ContactSummary;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentException;
use App\Domain\Documents\PaymentMethod;
use App\Domain\Mail\EmailSettings;
use App\Domain\Mail\EmailTemplate;
use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use App\Domain\Payments\IbanConverter;
use App\Domain\Payments\QrPaymentService;
use App\Domain\Pdf\InvoicePdf;
use App\Domain\Settings\DocumentSettings;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\EmailLog;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Editor faktury: nový koncept (nic se neukládá, dokud uživatel nepotvrdí), úprava konceptu, vystavení. */
class InvoiceController extends Controller
{
    public function create(Request $request, DocumentSettings $settings): Response
    {
        $contact = Contact::query()->whereNull('archived_at')->find($request->query('kontakt'));
        $today = Carbon::today();

        return $this->editor(null, $contact, [
            'contact_id' => $contact?->id,
            'issue_date' => $today->toDateString(),
            'due_date' => $today->copy()->addDays($settings->dueDaysFor($contact))->toDateString(),
            'taxable_date' => null,
            'show_taxable_date' => false,
            'order_number' => '',
            'payment_method' => PaymentMethod::BankTransfer->value,
            'bank_account_id' => BankAccount::default()?->id,
            'text_before' => $settings->textBefore() ?? '',
            'text_after' => $settings->textAfter() ?? '',
            'note_internal' => '',
            'discount_percent' => $contact->default_discount_percent ?? '0.00',
            'round_total' => $settings->roundTotal(),
            'items' => [],
        ], $settings);
    }

    public function edit(Document $invoice, DocumentSettings $settings): Response|RedirectResponse
    {
        if ($blocked = $this->redirectIfNotEditable($invoice)) {
            return $blocked;
        }

        $invoice->load(['items', 'contact']);

        return $this->editor($invoice, $invoice->contact, [
            'contact_id' => $invoice->contact_id,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'taxable_date' => $invoice->taxable_date?->toDateString(),
            'show_taxable_date' => $invoice->show_taxable_date,
            'order_number' => $invoice->order_number ?? '',
            'payment_method' => $invoice->payment_method->value ?? PaymentMethod::BankTransfer->value,
            'bank_account_id' => $invoice->bank_account_id ?? BankAccount::default()?->id,
            'text_before' => $invoice->text_before ?? '',
            'text_after' => $invoice->text_after ?? '',
            'note_internal' => $invoice->note_internal ?? '',
            'discount_percent' => $invoice->discount_percent,
            'round_total' => $invoice->round_total,
            'items' => $invoice->items->map(fn (DocumentItem $item): array => [
                'name' => $item->name,
                'description' => $item->description ?? '',
                'qty' => self::trimQty($item->qty),
                'unit' => $item->unit ?? '',
                'unit_price' => $item->unit_price,
                'price_item_id' => $item->price_item_id,
            ])->values()->all(),
        ], $settings);
    }

    public function store(Request $request, SaveInvoiceDraft $save): RedirectResponse
    {
        $invoice = $save($request->all());

        return redirect()->route('invoices.edit', $invoice)->with('status', 'Koncept uložen');
    }

    public function update(Request $request, Document $invoice, SaveInvoiceDraft $save): RedirectResponse
    {
        if ($blocked = $this->redirectIfNotEditable($invoice)) {
            return $blocked;
        }

        $save($request->all(), $invoice);

        return back()->with('status', 'Koncept uložen');
    }

    /** Vystaví nový koncept (uloží ho a rovnou vystaví). */
    public function issueNew(Request $request, SaveInvoiceDraft $save, IssueDocument $issue): RedirectResponse
    {
        return $this->saveAndIssue($save($request->all()), $issue, $request->boolean('send'));
    }

    /** Vystaví existující koncept; přiložená data formuláře se předtím uloží. */
    public function issue(Request $request, Document $invoice, SaveInvoiceDraft $save, IssueDocument $issue): RedirectResponse
    {
        if ($blocked = $this->redirectIfNotEditable($invoice)) {
            return $blocked;
        }

        if ($request->has('items')) {
            $invoice = $save($request->all(), $invoice);
        }

        return $this->saveAndIssue($invoice, $issue, $request->boolean('send'));
    }

    public function destroy(Document $invoice): RedirectResponse
    {
        $wasDraft = ! $invoice->isLocked();

        try {
            $invoice->delete();
        } catch (DocumentException $e) {
            return back()->with('error', ['message' => $e->getMessage()]);
        }

        return redirect()->route('invoices.index')->with('status', $wasDraft ? 'Koncept smazán' : 'Faktura smazána');
    }

    /** Kopie faktury jako nový koncept. */
    public function copy(Document $invoice, DuplicateInvoice $duplicate): RedirectResponse
    {
        $copy = $duplicate($invoice);

        return redirect()->route('invoices.edit', $copy)
            ->with('status', $invoice->number === null ? 'Koncept zkopírován' : "Koncept vytvořen kopií faktury {$invoice->number}");
    }

    /** Detail faktury; koncept se místo detailu otevře v editoru. */
    public function show(Document $invoice, ContactStatistics $statistics, EmailSettings $emailSettings, InvoicePdf $pdf): Response|RedirectResponse
    {
        if (! $invoice->isLocked() && $invoice->status === DocumentStatus::Draft) {
            return redirect()->route('invoices.edit', $invoice);
        }

        $invoice->load(['items', 'payments', 'contact', 'relatedDocument']);
        $today = Carbon::today();
        $payments = $invoice->payments;
        $lastPaymentId = $payments->last()?->id;
        $contact = $invoice->contact;
        $canPay = in_array($invoice->status, [DocumentStatus::Issued, DocumentStatus::Sent, DocumentStatus::PartiallyPaid], true)
            && $invoice->remainingAmount()->isPositive();
        $overdue = $invoice->computedStatus($today)?->value === 'overdue';
        $total = BigDecimal::of($invoice->total);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'computed_status' => $invoice->computedStatus($today)?->value,
                'overdue_days' => $overdue && $invoice->due_date !== null
                    ? (int) $invoice->due_date->copy()->startOfDay()->diffInDays($today, false)
                    : null,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'taxable_date' => $invoice->taxable_date?->toDateString(),
                'show_taxable_date' => $invoice->show_taxable_date,
                'issued_at' => $invoice->locked_at?->toIso8601String(),
                'sent_at' => $invoice->sent_at?->toIso8601String(),
                'paid_at' => $invoice->paid_at?->toDateString(),
                'vs' => $invoice->vs,
                'ks' => $invoice->ks,
                'order_number' => $invoice->order_number,
                'payment_method' => $invoice->payment_method->value ?? PaymentMethod::BankTransfer->value,
                'currency' => $invoice->currency,
                'subtotal' => $invoice->subtotal,
                'discount_percent' => $invoice->discount_percent,
                'vat_total' => $invoice->vat_total,
                'rounding' => $invoice->rounding,
                'total' => $invoice->total,
                'paid_total' => $invoice->paid_total,
                'remaining' => (string) $invoice->remainingAmount()->toScale(2),
                'paid_percent' => $total->isPositive()
                    ? min(100, BigDecimal::of($invoice->paid_total)->multipliedBy(100)->dividedBy($total, 0, RoundingMode::DOWN)->toInt())
                    : 0,
                'text_before' => $invoice->text_before,
                'text_after' => $invoice->text_after,
                'supplier' => $invoice->supplier_snapshot,
                'buyer' => $invoice->buyer_snapshot,
                'bank_account' => $this->bankForDisplay($invoice->bank_account_snapshot),
                'pdf_sha256' => $invoice->pdf_sha256,
                'qr_svg' => $this->qr($invoice),
                'can_pay' => $canPay,
                'can_delete' => $this->canDelete($invoice),
                'items' => $invoice->items->map(fn (DocumentItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'qty' => self::trimQty($item->qty),
                    'unit' => $item->unit ?? '',
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ])->values(),
                'payments' => $payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->toDateString(),
                    'method' => $payment->method->value,
                    'note' => $payment->note,
                    'deletable' => $payment->id === $lastPaymentId,
                ])->values(),
            ],
            'contact' => $contact === null ? null : [
                'id' => $contact->id,
                'name' => $contact->name,
                'ico' => $contact->ico,
                'stats' => $statistics->forContact($contact, $today)->toArray(),
            ],
            'related' => $this->related($invoice),
            'email' => $this->emailDraft($invoice, $contact, $emailSettings, $pdf, $overdue),
            'emails' => $invoice->emailLogs()->where('status', EmailLog::STATUS_SENT)->get()
                ->map(fn (EmailLog $log): array => [
                    'id' => $log->id,
                    'type' => $log->type,
                    'to' => $log->to_address,
                    'sent_at' => $log->sent_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    /**
     * Předvyplněný e-mail pro Sheet „Odeslat e-mailem“: příjemce a kopie z kontaktu, předmět a text
     * z šablon v Nastavení s dosazenými proměnnými (dají se před odesláním upravit).
     *
     * @return array<string, mixed>
     */
    private function emailDraft(Document $invoice, ?Contact $contact, EmailSettings $settings, InvoicePdf $pdf, bool $overdue): array
    {
        $templates = $settings->all();
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
            'attachment' => $pdf->filename($invoice),
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

    private function saveAndIssue(Document $invoice, IssueDocument $issue, bool $send = false): RedirectResponse
    {
        try {
            $invoice = $issue($invoice);
        } catch (DocumentException $e) {
            // Koncept zůstává uložený, uživatel nepřijde o práci.
            return redirect()->route('invoices.edit', $invoice)->with('error', IssueFailure::describe($e));
        }

        // „Vystavit a odeslat“: detail se otevře rovnou se Sheetem pro odeslání.
        return redirect()->route('invoices.show', $send ? [$invoice, 'odeslat' => 1] : [$invoice])->with('status', 'Faktura vystavena');
    }

    private function redirectIfNotEditable(Document $invoice): ?RedirectResponse
    {
        if ($invoice->isLocked() || $invoice->status !== DocumentStatus::Draft) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', ['message' => 'Vystavený doklad nejde upravovat.']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function editor(?Document $invoice, ?Contact $contact, array $form, DocumentSettings $settings): Response
    {
        $company = CompanyProfile::current();

        return Inertia::render('Invoices/Editor', [
            'document' => $invoice === null ? null : ['id' => $invoice->id],
            'form' => $form,
            'contact' => $contact === null ? null : ContactSummary::from($contact),
            'bankAccounts' => BankAccount::query()->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (BankAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'number' => $account->account_number.'/'.$account->bank_code,
                    'is_default' => $account->is_default,
                ])->values(),
            'defaults' => [
                'due_days' => $settings->dueDays(),
                'constant_symbol' => $settings->constantSymbol() ?? '',
                'round_total' => $settings->roundTotal(),
                'vat_payer' => (bool) $company?->vat_payer,
            ],
        ]);
    }

    /** Smazat jde koncept nebo poslední neodeslaná a neuhrazená faktura řady (pravidla má Document). */
    private function canDelete(Document $invoice): bool
    {
        try {
            $invoice->assertDeletable();
        } catch (DocumentException) {
            return false;
        }

        return true;
    }

    /**
     * Účet ze snapshotu pro zobrazení: IBAN po čtveřicích.
     *
     * @param  array<string, mixed>|null  $account
     * @return array<string, mixed>|null
     */
    private function bankForDisplay(?array $account): ?array
    {
        if ($account !== null && ! empty($account['iban'])) {
            $account['iban'] = (new IbanConverter)->pretty((string) $account['iban']);
        }

        return $account;
    }

    /** QR Platba pro náhled; faktura bez účtu nebo bez zbývající částky ji nemá. */
    private function qr(Document $invoice): ?string
    {
        if ($invoice->payment_method !== PaymentMethod::BankTransfer || $invoice->bank_account_snapshot === null) {
            return null;
        }

        try {
            return app(QrPaymentService::class)->forDocument($invoice);
        } catch (InvalidPaymentDataException) {
            return null;
        }
    }

    /**
     * Související doklady: ten, na který faktura odkazuje, a doklady, které odkazují na ni.
     *
     * @return list<array{id: int, type: string, number: string|null, status: string, url: string|null}>
     */
    private function related(Document $invoice): array
    {
        $documents = Document::query()->where('related_document_id', $invoice->id)->get();
        if ($invoice->relatedDocument !== null) {
            $documents->prepend($invoice->relatedDocument);
        }

        return $documents->map(fn (Document $document): array => [
            'id' => $document->id,
            'type' => $document->type->value,
            'number' => $document->number,
            'status' => $document->status->value,
            'url' => $document->type === DocumentType::Invoice ? route('invoices.show', $document) : null,
        ])->values()->all();
    }

    private static function trimQty(string $qty): string
    {
        return str_contains($qty, '.') ? rtrim(rtrim($qty, '0'), '.') : $qty;
    }
}
