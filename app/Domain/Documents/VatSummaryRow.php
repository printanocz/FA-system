<?php

namespace App\Domain\Documents;

use Brick\Math\BigDecimal;

/** Rekapitulace DPH: základ a daň za jednu sazbu. */
final readonly class VatSummaryRow
{
    public function __construct(
        public BigDecimal $rate,
        public BigDecimal $base,
        public BigDecimal $vat,
    ) {}
}
