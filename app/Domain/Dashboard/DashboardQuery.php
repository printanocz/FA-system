<?php

namespace App\Domain\Dashboard;

use App\Domain\Contacts\ContactStatistics;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\Document;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Data Přehledu. Počítají se jen faktury (`invoice`) ve stavech vystavená, odeslaná, částečně
 * uhrazená a uhrazená: koncepty a stornované doklady ne. Období se řídí datem vystavení, částky
 * jsou v měně dokladu bez přepočtu (kurzy přijdou s T-25) a sčítají se přes brick/math.
 *
 * Celý Přehled stojí na čtyřech dotazech: faktury za letošek a loňsko (jen datum a částka),
 * otevřené faktury, posledních pět dokladů a „existuje vystavená faktura“.
 */
final class DashboardQuery
{
    public const PERIODS = ['mesic', 'kvartal', 'rok'];

    public const DEFAULT_PERIOD = 'rok';

    /** Kolik faktur po splatnosti se ukáže v seznamu. */
    public const OVERDUE_LIMIT = 5;

    public const RECENT_LIMIT = 5;

    private readonly CarbonImmutable $today;

    public function __construct(?CarbonInterface $today = null)
    {
        $this->today = CarbonImmutable::instance($today ?? Carbon::today())->startOfDay();
    }

    public static function normalizePeriod(?string $period): string
    {
        return in_array($period, self::PERIODS, true) ? (string) $period : self::DEFAULT_PERIOD;
    }

    /** @return array<string, mixed> */
    public function data(string $period = self::DEFAULT_PERIOD): array
    {
        $period = self::normalizePeriod($period);

        if (! $this->hasIssuedInvoice()) {
            return ['empty' => true, 'period' => $period, 'today' => $this->today->toDateString()];
        }

        $rows = $this->issuedRows();
        $open = $this->openInvoices();

        return [
            'empty' => false,
            'period' => $period,
            'today' => $this->today->toDateString(),
            'cards' => [
                'billed' => $this->billed($rows, $period),
                'month' => $this->billed($rows, 'mesic'),
                'unpaid' => $this->unpaid($open),
                'overdue' => $this->overdue($open),
            ],
            'chart' => $this->chart($rows),
            'vat' => $this->vat($rows),
            'overdue_list' => $this->overdueList($open),
            'recent' => $this->recent(),
        ];
    }

    /**
     * Rozsah aktuálního období od jeho začátku do dneška a stejného období loni (do stejného dne).
     * 29. 2. se loni mapuje na 28. 2.
     *
     * @return array{current: array{0: CarbonImmutable, 1: CarbonImmutable}, previous: array{0: CarbonImmutable, 1: CarbonImmutable}}
     */
    public function ranges(string $period): array
    {
        $today = $this->today;
        $start = match ($period) {
            'mesic' => $today->startOfMonth(),
            'kvartal' => $today->startOfQuarter(),
            default => $today->startOfYear(),
        };

        return [
            'current' => [$start, $today],
            'previous' => [$start->subYearNoOverflow(), $today->subYearNoOverflow()],
        ];
    }

    /**
     * Lehký výběr vystavených faktur od 1. 1. loňského roku do dneška: datum vystavení a částka.
     *
     * @return list<array{date: string, total: BigDecimal}>
     */
    private function issuedRows(): array
    {
        $from = $this->today->subYears(1)->startOfYear()->toDateString();

        return Document::query()
            ->where('type', DocumentType::Invoice)
            ->whereIn('status', [...ContactStatistics::ISSUED_STATUSES])
            ->whereBetween('issue_date', [$from, $this->today->toDateString()])
            ->toBase()
            ->get(['issue_date', 'total'])
            ->map(fn (object $row): array => [
                'date' => substr((string) $row->issue_date, 0, 10),
                'total' => BigDecimal::of((string) $row->total),
            ])
            ->all();
    }

    private function hasIssuedInvoice(): bool
    {
        return Document::query()
            ->where('type', DocumentType::Invoice)
            ->whereIn('status', [...ContactStatistics::ISSUED_STATUSES])
            ->exists();
    }

    /**
     * @param  list<array{date: string, total: BigDecimal}>  $rows
     */
    private function sum(array $rows, string $from, string $to): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($rows as $row) {
            if ($row['date'] >= $from && $row['date'] <= $to) {
                $sum = $sum->plus($row['total']);
            }
        }

        return $sum;
    }

    /**
     * Fakturováno za období a změna oproti stejnému období loni. Bez loňských dat je změna null.
     *
     * @param  list<array{date: string, total: BigDecimal}>  $rows
     * @return array{amount: string, previous: string, change: int|null, from: string, to: string}
     */
    private function billed(array $rows, string $period): array
    {
        $ranges = $this->ranges($period);
        $current = $this->sum($rows, $ranges['current'][0]->toDateString(), $ranges['current'][1]->toDateString());
        $previous = $this->sum($rows, $ranges['previous'][0]->toDateString(), $ranges['previous'][1]->toDateString());

        return [
            'amount' => (string) $current->toScale(2),
            'previous' => (string) $previous->toScale(2),
            'change' => self::change($current, $previous),
            'from' => $ranges['current'][0]->toDateString(),
            'to' => $ranges['current'][1]->toDateString(),
        ];
    }

    /** Procentní změna zaokrouhlená na celá procenta; bez základu (loni nic) null. */
    public static function change(BigDecimal $current, BigDecimal $previous): ?int
    {
        if (! $previous->isPositive()) {
            return null;
        }

        return $current->minus($previous)
            ->multipliedBy(100)
            ->dividedBy($previous, 0, RoundingMode::HALF_UP)
            ->toInt();
    }

    /**
     * 12 měsíců letos a loni. Budoucí měsíce letos mají amount null (prázdné sloupce).
     *
     * @param  list<array{date: string, total: BigDecimal}>  $rows
     * @return array{year: int, previous_year: int, months: list<array<string, mixed>>, strongest: array{month: int, amount: string}|null, total: string}
     */
    private function chart(array $rows): array
    {
        $year = $this->today->year;
        $monthly = [];
        foreach ($rows as $row) {
            $key = substr($row['date'], 0, 7);
            $monthly[$key] = ($monthly[$key] ?? BigDecimal::zero())->plus($row['total']);
        }

        $months = [];
        $strongest = null;
        $total = BigDecimal::zero();
        for ($month = 1; $month <= 12; $month++) {
            $future = $month > $this->today->month;
            $current = $monthly[sprintf('%04d-%02d', $year, $month)] ?? BigDecimal::zero();
            $previous = $monthly[sprintf('%04d-%02d', $year - 1, $month)] ?? BigDecimal::zero();

            $months[] = [
                'month' => $month,
                'current' => $future ? null : (string) $current->toScale(2),
                'previous' => (string) $previous->toScale(2),
            ];

            if (! $future) {
                $total = $total->plus($current);
                if ($current->isPositive() && ($strongest === null || $current->isGreaterThan($strongest['amount']))) {
                    $strongest = ['month' => $month, 'amount' => $current];
                }
            }
        }

        return [
            'year' => $year,
            'previous_year' => $year - 1,
            'months' => $months,
            'strongest' => $strongest === null ? null : ['month' => $strongest['month'], 'amount' => (string) $strongest['amount']->toScale(2)],
            'total' => (string) $total->toScale(2),
        ];
    }

    /**
     * Obrat za kalendářní rok vůči limitu pro registraci k DPH.
     *
     * @param  list<array{date: string, total: BigDecimal}>  $rows
     * @return array<string, mixed>
     */
    private function vat(array $rows): array
    {
        $turnover = $this->sum($rows, $this->today->startOfYear()->toDateString(), $this->today->toDateString());
        $limit = BigDecimal::of((string) config('vat.registration_limit'));
        $immediate = BigDecimal::of((string) config('vat.immediate_limit'));
        $warningPercent = (int) config('vat.warning_percent');

        $remaining = $limit->minus($turnover);

        return [
            'year' => $this->today->year,
            'turnover' => (string) $turnover->toScale(2),
            'limit' => (string) $limit->toScale(0),
            'immediate_limit' => (string) $immediate->toScale(0),
            'percent' => $turnover->multipliedBy(100)->dividedBy($limit, 0, RoundingMode::HALF_UP)->toInt(),
            'remaining' => (string) ($remaining->isNegative() ? BigDecimal::zero() : $remaining)->toScale(2),
            'warning' => $turnover->multipliedBy(100)->isGreaterThanOrEqualTo($limit->multipliedBy($warningPercent)),
            'exceeded' => $turnover->isGreaterThan($limit),
            'exceeded_immediate' => $turnover->isGreaterThan($immediate),
        ];
    }

    /**
     * Otevřené faktury s neuhrazenou částkou (jeden dotaz pro karty Neuhrazeno a Po splatnosti).
     *
     * @return list<array<string, mixed>>
     */
    private function openInvoices(): array
    {
        return Document::query()
            ->where('type', DocumentType::Invoice)
            ->whereIn('status', [...ContactStatistics::OPEN_STATUSES])
            ->whereColumn('paid_total', '<', 'total')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'number', 'buyer_snapshot', 'due_date', 'total', 'paid_total', 'currency'])
            ->map(fn (Document $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'contact' => $invoice->buyer_snapshot['name'] ?? null,
                'due_date' => $invoice->due_date?->toDateString(),
                'remaining' => $invoice->remainingAmount(),
                'currency' => $invoice->currency,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $open
     * @return array{amount: string, count: int, nearest_due: array{date: string, days: int}|null}
     */
    private function unpaid(array $open): array
    {
        $sum = BigDecimal::zero();
        $nearest = null;
        $today = $this->today->toDateString();

        foreach ($open as $invoice) {
            $sum = $sum->plus($invoice['remaining']);
            // Nejbližší splatnost: první, která ještě nenastala (dnes se počítá). Seznam je řazený podle splatnosti.
            if ($nearest === null && $invoice['due_date'] !== null && $invoice['due_date'] >= $today) {
                $nearest = [
                    'date' => $invoice['due_date'],
                    'days' => (int) $this->today->diffInDays(CarbonImmutable::parse($invoice['due_date']), false),
                ];
            }
        }

        return ['amount' => (string) $sum->toScale(2), 'count' => count($open), 'nearest_due' => $nearest];
    }

    /**
     * @param  list<array<string, mixed>>  $open
     * @return array{amount: string, count: int}
     */
    private function overdue(array $open): array
    {
        $sum = BigDecimal::zero();
        $count = 0;
        foreach ($this->overdueInvoices($open) as $invoice) {
            $sum = $sum->plus($invoice['remaining']);
            $count++;
        }

        return ['amount' => (string) $sum->toScale(2), 'count' => $count];
    }

    /**
     * @param  list<array<string, mixed>>  $open
     * @return list<array<string, mixed>>
     */
    private function overdueInvoices(array $open): array
    {
        $today = $this->today->toDateString();

        return array_values(array_filter(
            $open,
            fn (array $invoice): bool => $invoice['due_date'] !== null && $invoice['due_date'] < $today,
        ));
    }

    /**
     * Pět nejdéle po splatnosti.
     *
     * @param  list<array<string, mixed>>  $open
     * @return list<array<string, mixed>>
     */
    private function overdueList(array $open): array
    {
        return array_map(fn (array $invoice): array => [
            'id' => $invoice['id'],
            'number' => $invoice['number'],
            'contact' => $invoice['contact'],
            'due_date' => $invoice['due_date'],
            'overdue_days' => (int) CarbonImmutable::parse($invoice['due_date'])->diffInDays($this->today),
            'remaining' => (string) $invoice['remaining']->toScale(2),
            'currency' => $invoice['currency'],
        ], array_slice($this->overdueInvoices($open), 0, self::OVERDUE_LIMIT));
    }

    /**
     * Posledních pět vystavených faktur ve stejném tvaru jako řádek seznamu faktur.
     *
     * @return list<array<string, mixed>>
     */
    private function recent(): array
    {
        return Document::query()
            ->where('type', DocumentType::Invoice)
            ->whereIn('status', [...ContactStatistics::ISSUED_STATUSES, DocumentStatus::Cancelled])
            ->orderByDesc('issue_date')
            ->orderByDesc('number_year')
            ->orderByDesc('number_seq')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get(['id', 'type', 'number', 'status', 'buyer_snapshot', 'issue_date', 'due_date', 'total', 'paid_total', 'currency'])
            ->map(function (Document $invoice): array {
                $computed = $invoice->computedStatus($this->today);

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'status' => $invoice->status->value,
                    'computed_status' => $computed?->value,
                    'overdue_days' => $computed?->value === 'overdue' && $invoice->due_date !== null
                        ? (int) $invoice->due_date->copy()->startOfDay()->diffInDays($this->today, false)
                        : null,
                    'contact' => $invoice->buyer_snapshot['name'] ?? null,
                    'issue_date' => $invoice->issue_date?->toDateString(),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'total' => $invoice->total,
                    'currency' => $invoice->currency,
                ];
            })
            ->all();
    }
}
