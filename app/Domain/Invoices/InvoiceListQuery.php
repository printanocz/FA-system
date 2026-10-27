<?php

namespace App\Domain\Invoices;

use App\Domain\Contacts\ContactStatistics;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\Document;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Dotazy nad seznamem faktur: filtry (rok, odběratel, hledání), segmenty stavu s počty,
 * součty za aktuální filtr a výchozí řazení. Jediné místo, kde se skládá SQL seznamu.
 */
final class InvoiceListQuery
{
    public const SEGMENTS = ['vse', 'neuhrazene', 'po-splatnosti', 'uhrazene', 'koncepty'];

    public const PER_PAGE = 25;

    /** Stavy vystavené faktury (do součtů se nepočítají koncepty ani storna). */
    private const ISSUED = ContactStatistics::ISSUED_STATUSES;

    private readonly CarbonInterface $today;

    public function __construct(
        public readonly ?int $year,
        public readonly ?int $contactId,
        public readonly string $search,
        public readonly string $segment = 'vse',
        ?CarbonInterface $today = null,
    ) {
        $this->today = $today ?? Carbon::today();
    }

    /** Základ: faktury po filtru roku, odběratele a hledání, ještě bez segmentu. */
    public function base(): Builder
    {
        return Document::query()
            ->where('type', DocumentType::Invoice)
            ->when($this->year !== null, function (Builder $query): void {
                $from = sprintf('%04d-01-01', $this->year);
                $to = sprintf('%04d-12-31', $this->year);
                // Koncept bez data vystavení patří do každého roku, ať se neztratí.
                $query->where(fn (Builder $q) => $q->whereBetween('issue_date', [$from, $to])->orWhereNull('issue_date'));
            })
            ->when($this->contactId !== null, fn (Builder $query) => $query->where('contact_id', $this->contactId))
            ->when($this->search !== '', fn (Builder $query) => $this->applySearch($query));
    }

    /** Seznam v aktuálním segmentu. */
    public function list(): Builder
    {
        return $this->withSegment($this->base(), $this->segment);
    }

    /** Výchozí řazení: koncepty nahoře, pak datum vystavení a číslo sestupně. */
    public function ordered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('issue_date')
            ->orderByDesc('number_year')
            ->orderByDesc('number_seq')
            ->orderByDesc('id');
    }

    /**
     * Počty v segmentech (stejný rok, odběratel a hledání, jen bez výběru segmentu).
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];
        foreach (self::SEGMENTS as $segment) {
            $counts[$segment] = $this->withSegment($this->base(), $segment)->count();
        }

        return $counts;
    }

    /**
     * Součty za aktuální filtr z vystavených faktur: celkem, uhrazeno, neuhrazeno, po splatnosti.
     *
     * @return array{count: int, total: string, paid: string, unpaid: string, overdue: string}
     */
    public function totals(): array
    {
        $issued = $this->list()->whereIn('status', [...self::ISSUED]);

        $count = (clone $issued)->count();
        $total = BigDecimal::of((string) ((clone $issued)->sum('total') ?: '0'));
        $paid = BigDecimal::of((string) ((clone $issued)->sum('paid_total') ?: '0'));

        $overdue = $this->overdue(clone $issued)
            ->selectRaw('COALESCE(SUM(total - paid_total), 0) as amount')
            ->value('amount');

        return [
            'count' => $count,
            'total' => (string) $total->toScale(2),
            'paid' => (string) $paid->toScale(2),
            'unpaid' => (string) $total->minus($paid)->toScale(2),
            'overdue' => (string) BigDecimal::of((string) $overdue)->toScale(2),
        ];
    }

    /** Po splatnosti: otevřená faktura se splatností před dneškem a neuhrazenou částkou. */
    public function overdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [...ContactStatistics::OPEN_STATUSES])
            ->whereDate('due_date', '<', $this->today->toDateString())
            ->whereColumn('paid_total', '<', 'total');
    }

    /** Počet faktur po splatnosti napříč roky (štítek v Sidebaru), jeden COUNT. */
    public static function overdueCount(?CarbonInterface $today = null): int
    {
        return (new self(null, null, '', 'vse', $today))
            ->overdue(Document::query()->where('type', DocumentType::Invoice))
            ->count();
    }

    private function withSegment(Builder $query, string $segment): Builder
    {
        return match ($segment) {
            'neuhrazene' => $query->whereIn('status', [...ContactStatistics::OPEN_STATUSES]),
            'po-splatnosti' => $this->overdue($query),
            'uhrazene' => $query->where('status', DocumentStatus::Paid),
            'koncepty' => $query->where('status', DocumentStatus::Draft),
            default => $query,
        };
    }

    /** Číslo, odběratel (jméno ze snapshotu i z kontaktu), popis položky a částka. */
    private function applySearch(Builder $query): void
    {
        $term = $this->search;
        $like = '%'.addcslashes($term, '\\%_').'%';
        $amount = self::normalizeAmount($term);

        $query->where(function (Builder $q) use ($like, $amount): void {
            $q->where('number', 'like', $like)
                ->orWhere('buyer_snapshot->name', 'like', $like)
                ->orWhereHas('contact', fn (BuilderContract $contact) => $contact->where('name', 'like', $like))
                ->orWhereHas('items', fn (BuilderContract $item) => $item
                    ->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like));

            if ($amount !== null) {
                $q->orWhere('total', $amount)->orWhere('paid_total', $amount);
            }
        });
    }

    /**
     * Zadaná částka v české podobě („9 800,00 Kč“, „9800“, „24500.5“) jako číslo pro porovnání
     * s `total`; text, který číslo není, vrátí null.
     */
    public static function normalizeAmount(string $term): ?string
    {
        $clean = str_ireplace(['kč', 'czk'], '', $term);
        $clean = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $clean);
        $clean = str_replace(',', '.', $clean);

        return preg_match('/^\d+(\.\d{1,2})?$/', $clean) === 1 ? $clean : null;
    }
}
