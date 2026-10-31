<?php

namespace App\Actions\Documents;

use App\Domain\Documents\CalculatorLine;
use App\Domain\Documents\DocumentCalculator;
use App\Domain\Documents\DocumentTotals;
use App\Models\Document;
use App\Models\DocumentItem;
use Illuminate\Support\Facades\DB;

/**
 * Přepočítá částky konceptu: line_total u položek a subtotal, vat_total, rounding
 * a total na dokladu. U zamčeného dokladu skončí doménovou výjimkou (zamčená pole).
 */
final class RecalculateDocument
{
    public function __construct(private readonly DocumentCalculator $calculator) {}

    /**
     * @param  bool  $save  false = hodnoty se jen nastaví na modelu (uloží je volající)
     */
    public function __invoke(Document $document, bool $vatPayer, bool $save = true): DocumentTotals
    {
        return DB::transaction(function () use ($document, $vatPayer, $save): DocumentTotals {
            $items = $document->items()->get();

            $totals = $this->calculator->calculate(
                lines: $items->map(fn (DocumentItem $item): CalculatorLine => new CalculatorLine(
                    $item->qty,
                    $item->unit_price,
                    $item->discount_percent,
                    $item->vat_rate,
                ))->all(),
                discountPercent: $document->discount_percent,
                roundTotal: $document->round_total,
                vatPayer: $vatPayer,
            );

            foreach ($items->values() as $index => $item) {
                $item->line_total = (string) $totals->lineTotals[$index];
                $item->save();
            }

            $document->fill($totals->toColumns());

            if ($save) {
                $document->save();
            }

            return $totals;
        });
    }
}
