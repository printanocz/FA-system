<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Documents\MarkInvoicesPaid;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentException;
use App\Domain\Invoices\InvoiceListQuery;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seznam faktur vydaných: segmenty stavu s počty, filtry (rok, odběratel), hledání,
 * součty za filtr a stránkování po 25. Hromadné označení jako uhrazené.
 */
class InvoiceListController extends Controller
{
    public function index(Request $request): Response
    {
        $segment = (string) $request->query('stav', 'vse');
        $segment = in_array($segment, InvoiceListQuery::SEGMENTS, true) ? $segment : 'vse';

        $currentYear = Carbon::today()->year;
        $rawYear = (string) $request->query('rok', (string) $currentYear);
        $year = $rawYear === 'vse' ? null : (ctype_digit($rawYear) ? (int) $rawYear : $currentYear);
        $contactId = ctype_digit((string) $request->query('odberatel', '')) ? (int) $request->query('odberatel') : null;
        $search = trim((string) $request->query('q', ''));

        $query = new InvoiceListQuery($year, $contactId, $search, $segment);

        $page = $query->ordered($query->list())
            ->with(['contact:id,name', 'items:id,document_id,name,position'])
            ->paginate(InvoiceListQuery::PER_PAGE, ['*'], 'strana')
            ->withQueryString();

        $today = Carbon::today();
        $latestInSeries = $this->latestInSeries($page->getCollection());

        return Inertia::render('Invoices/Index', [
            'invoices' => $page->getCollection()->map(fn (Document $invoice): array => $this->row($invoice, $today, $latestInSeries))->values(),
            'pagination' => [
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'per_page' => $page->perPage(),
            ],
            'filters' => [
                'stav' => $segment,
                'rok' => $year === null ? 'vse' : (string) $year,
                'odberatel' => $contactId,
                'q' => $search,
            ],
            'counts' => $query->counts(),
            'totals' => $query->totals(),
            'years' => $this->years($currentYear),
            'contacts' => $this->contacts(),
            'hasInvoices' => Document::query()->where('type', DocumentType::Invoice)->exists(),
        ]);
    }

    /** Hromadné „Označit jako uhrazené“: plná úhrada k dnešnímu dni, neplatné přeskočí. */
    public function pay(Request $request, MarkInvoicesPaid $markPaid): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'Vyberte aspoň jednu fakturu.',
            'ids.min' => 'Vyberte aspoň jednu fakturu.',
        ]);

        $result = $markPaid($data['ids']);

        if ($result['paid'] === 0) {
            return back()->with('error', ['message' => $result['skipped'] === 1
                ? 'Tuto fakturu nejde označit jako uhrazenou (koncept, už uhrazená nebo stornovaná).'
                : 'Žádnou z vybraných faktur nejde označit jako uhrazenou (koncepty, uhrazené nebo stornované).']);
        }

        $message = $result['paid'] === 1
            ? 'Faktura označena jako uhrazená'
            : 'Označeno jako uhrazené: '.$result['paid'].' '.self::plural($result['paid']);

        if ($result['skipped'] > 0) {
            $message .= '. Přeskočeno '.$result['skipped'].' (koncept, uhrazená nebo stornovaná)';
        }

        return back()->with('status', $message);
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<int, int> poslední doklad řady podle id řady
     */
    private function latestInSeries($documents): array
    {
        $latest = [];
        foreach ($documents->pluck('series_id')->filter()->unique() as $seriesId) {
            $latest[(int) $seriesId] = (int) Document::query()
                ->where('series_id', $seriesId)
                ->orderByDesc('number_year')
                ->orderByDesc('number_seq')
                ->value('id');
        }

        return $latest;
    }

    /**
     * @param  array<int, int>  $latestInSeries
     * @return array<string, mixed>
     */
    private function row(Document $invoice, Carbon $today, array $latestInSeries): array
    {
        $overdue = $invoice->computedStatus($today)?->value === 'overdue';

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'computed_status' => $invoice->computedStatus($today)?->value,
            'overdue_days' => $overdue && $invoice->due_date !== null
                ? (int) $invoice->due_date->copy()->startOfDay()->diffInDays($today, false)
                : null,
            'contact_id' => $invoice->contact_id,
            'contact' => $invoice->buyer_snapshot['name'] ?? $invoice->contact?->name,
            'description' => $invoice->items->first()?->name,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'total' => $invoice->total,
            'paid_total' => $invoice->paid_total,
            'currency' => $invoice->currency,
            'can_pay' => $this->canPay($invoice),
            'can_delete' => $this->canDelete($invoice, $latestInSeries),
        ];
    }

    private function canPay(Document $invoice): bool
    {
        return in_array($invoice->status, [DocumentStatus::Issued, DocumentStatus::Sent, DocumentStatus::PartiallyPaid], true)
            && $invoice->remainingAmount()->isPositive();
    }

    /**
     * Smazat jde koncept, nebo poslední neodeslaná a neuhrazená faktura řady. Levná předkontrola
     * a přesné pravidlo z Document::assertDeletable jen u jediného kandidáta v řadě.
     *
     * @param  array<int, int>  $latestInSeries
     */
    private function canDelete(Document $invoice, array $latestInSeries): bool
    {
        if (! $invoice->isLocked()) {
            return $invoice->status === DocumentStatus::Draft;
        }

        if ($invoice->status !== DocumentStatus::Issued || $invoice->sent_at !== null || $invoice->series_id === null) {
            return false;
        }

        if (($latestInSeries[$invoice->series_id] ?? null) !== $invoice->id) {
            return false;
        }

        try {
            $invoice->assertDeletable();
        } catch (DocumentException) {
            return false;
        }

        return true;
    }

    /** @return list<int> */
    private function years(int $currentYear): array
    {
        // Rozdílná data stačí: je jich nejvýš pár stovek za rok a nezávisí to na SQL funkci pro rok.
        $years = Document::query()
            ->where('type', DocumentType::Invoice)
            ->whereNotNull('issue_date')
            ->distinct()
            ->pluck('issue_date')
            ->map(fn ($date): int => Carbon::parse($date)->year)
            ->push($currentYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years;
    }

    /** @return list<array{id: int, name: string}> */
    private function contacts(): array
    {
        return Contact::query()
            ->whereHas('documents', fn ($query) => $query->where('type', DocumentType::Invoice))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Contact $contact): array => ['id' => $contact->id, 'name' => $contact->name])
            ->all();
    }

    private static function plural(int $count): string
    {
        return match (true) {
            $count === 1 => 'faktura',
            $count >= 2 && $count <= 4 => 'faktury',
            default => 'faktur',
        };
    }
}
