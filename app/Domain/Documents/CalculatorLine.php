<?php

namespace App\Domain\Documents;

use Brick\Math\BigDecimal;

/** Vstupní řádek kalkulátoru: čísla jako řetězce nebo čísla, uvnitř přesný BigDecimal. */
final readonly class CalculatorLine
{
    public BigDecimal $qty;

    public BigDecimal $unitPrice;

    public BigDecimal $discountPercent;

    public BigDecimal $vatRate;

    public function __construct(
        string|int|float|BigDecimal $qty,
        string|int|float|BigDecimal $unitPrice,
        string|int|float|BigDecimal $discountPercent = 0,
        string|int|float|BigDecimal $vatRate = 0,
    ) {
        $this->qty = BigDecimal::of($qty);
        $this->unitPrice = BigDecimal::of($unitPrice);
        $this->discountPercent = BigDecimal::of($discountPercent);
        $this->vatRate = BigDecimal::of($vatRate);
    }
}
