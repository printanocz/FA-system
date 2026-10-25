<?php

namespace App\Domain\Documents;

use Brick\Math\BigDecimal;
use Brick\Money\Money;

/**
 * Výsledek DocumentCalculator. Všechny částky mají měřítko 2.
 *
 * total = subtotal − discountTotal + vatTotal + rounding
 */
final readonly class DocumentTotals
{
    /**
     * @param  list<BigDecimal>  $lineTotals  Částky řádků po slevě na řádek, ve stejném pořadí jako vstup.
     * @param  list<VatSummaryRow>  $vatSummary  Prázdné u neplátce DPH.
     */
    public function __construct(
        public array $lineTotals,
        public BigDecimal $subtotal,
        public BigDecimal $discountTotal,
        public BigDecimal $vatTotal,
        public BigDecimal $rounding,
        public BigDecimal $total,
        public array $vatSummary = [],
    ) {}

    /**
     * Hodnoty pro sloupce tabulky documents.
     *
     * @return array{subtotal: string, vat_total: string, rounding: string, total: string}
     */
    public function toColumns(): array
    {
        return [
            'subtotal' => (string) $this->subtotal,
            'vat_total' => (string) $this->vatTotal,
            'rounding' => (string) $this->rounding,
            'total' => (string) $this->total,
        ];
    }

    public function totalAsMoney(string $currency): Money
    {
        return Money::of($this->total, $currency);
    }
}
