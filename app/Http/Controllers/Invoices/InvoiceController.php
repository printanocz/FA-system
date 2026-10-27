<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Documents\IssueDocument;
use App\Actions\Documents\SaveInvoiceDraft;
use App\Domain\Contacts\ContactSummary;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentException;
use App\Domain\Documents\PaymentMethod;
use App\Domain\Settings\DocumentSettings;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
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

    public function edit(Document $document, DocumentSettings $settings): Response|RedirectResponse
    {
        if ($blocked = $this->redirectIfNotEditable($document)) {
            return $blocked;
        }

        $document->load(['items', 'contact']);

        return $this->editor($document, $document->contact, [
            'contact_id' => $document->contact_id,
            'issue_date' => $document->issue_date?->toDateString(),
            'due_date' => $document->due_date?->toDateString(),
            'taxable_date' => $document->taxable_date?->toDateString(),
            'order_number' => $document->order_number ?? '',
            'payment_method' => $document->payment_method->value ?? PaymentMethod::BankTransfer->value,
            'bank_account_id' => $document->bank_account_id ?? BankAccount::default()?->id,
            'text_before' => $document->text_before ?? '',
            'text_after' => $document->text_after ?? '',
            'note_internal' => $document->note_internal ?? '',
            'discount_percent' => $document->discount_percent,
            'round_total' => $document->round_total,
            'items' => $document->items->map(fn (DocumentItem $item): array => [
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
        $document = $save($request->all());

        return redirect()->route('invoices.edit', $document)->with('status', 'Koncept uložen');
    }

    public function update(Request $request, Document $document, SaveInvoiceDraft $save): RedirectResponse
    {
        if ($blocked = $this->redirectIfNotEditable($document)) {
            return $blocked;
        }

        $save($request->all(), $document);

        return back()->with('status', 'Koncept uložen');
    }

    /** Vystaví nový koncept (uloží ho a rovnou vystaví). */
    public function issueNew(Request $request, SaveInvoiceDraft $save, IssueDocument $issue): RedirectResponse
    {
        return $this->saveAndIssue($save($request->all()), $issue);
    }

    /** Vystaví existující koncept; přiložená data formuláře se předtím uloží. */
    public function issue(Request $request, Document $document, SaveInvoiceDraft $save, IssueDocument $issue): RedirectResponse
    {
        if ($blocked = $this->redirectIfNotEditable($document)) {
            return $blocked;
        }

        if ($request->has('items')) {
            $document = $save($request->all(), $document);
        }

        return $this->saveAndIssue($document, $issue);
    }

    public function destroy(Document $document): RedirectResponse
    {
        try {
            $document->delete();
        } catch (DocumentException $e) {
            return back()->with('error', ['message' => $e->getMessage()]);
        }

        return redirect('/')->with('status', 'Koncept smazán');
    }

    /** Dočasný detail jen pro čtení, nahradí ho T-09. */
    public function show(Document $document): Response
    {
        $document->load(['items', 'contact']);

        return Inertia::render('Invoices/Show', [
            'document' => [
                'id' => $document->id,
                'number' => $document->number,
                'status' => $document->status->value,
                'computed_status' => $document->computedStatus()?->value,
                'contact' => $document->buyer_snapshot['name'] ?? $document->contact?->name,
                'issue_date' => $document->issue_date?->toDateString(),
                'due_date' => $document->due_date?->toDateString(),
                'vs' => $document->vs,
                'currency' => $document->currency,
                'subtotal' => $document->subtotal,
                'rounding' => $document->rounding,
                'total' => $document->total,
                'editable' => ! $document->isLocked() && $document->status === DocumentStatus::Draft,
                'items' => $document->items->map(fn (DocumentItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'qty' => self::trimQty($item->qty),
                    'unit' => $item->unit ?? '',
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ])->values(),
            ],
        ]);
    }

    private function saveAndIssue(Document $document, IssueDocument $issue): RedirectResponse
    {
        try {
            $document = $issue($document);
        } catch (DocumentException $e) {
            // Koncept zůstává uložený, uživatel nepřijde o práci.
            return redirect()->route('invoices.edit', $document)->with('error', IssueFailure::describe($e));
        }

        return redirect()->route('invoices.show', $document)->with('status', 'Faktura vystavena');
    }

    private function redirectIfNotEditable(Document $document): ?RedirectResponse
    {
        if ($document->type !== DocumentType::Invoice) {
            abort(404);
        }

        if ($document->isLocked() || $document->status !== DocumentStatus::Draft) {
            return redirect()->route('invoices.show', $document)
                ->with('error', ['message' => 'Vystavený doklad nejde upravovat.']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function editor(?Document $document, ?Contact $contact, array $form, DocumentSettings $settings): Response
    {
        $company = CompanyProfile::current();

        return Inertia::render('Invoices/Editor', [
            'document' => $document === null ? null : ['id' => $document->id],
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

    private static function trimQty(string $qty): string
    {
        return str_contains($qty, '.') ? rtrim(rtrim($qty, '0'), '.') : $qty;
    }
}
