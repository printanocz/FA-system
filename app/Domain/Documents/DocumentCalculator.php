<?php

namespace App\Domain\Documents;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Výpočet částek dokladu. Čisté PHP bez databáze, přesná aritmetika (BigDecimal).
 *
 * Pravidla:
 *  - řádek = množství × cena × (1 − sleva řádku), zaokrouhleno na haléře (half up),
 *  - mezisoučet = součet řádků, sleva na doklad = mezisoučet × sleva %, zaokrouhleno na haléře,
 *  - zaokrouhlení half up = od nuly při shodě (0,005 → 0,01; −0,005 → −0,01),
 *  - neplátce DPH: vat_total = 0 bez ohledu na sazby u položek,
 *  - plátce DPH: ceny jsou bez DPH, daň se počítá po sazbách ze základu po slevě na doklad,
 *  - zaokrouhlení celkové částky na celé jednotky měny je volitelné (half up).
 */
final class DocumentCalculator
{
    public const SCALE = 2;

    /**
     * @param  list<CalculatorLine>  $lines
     */
    public function calculate(
        array $lines,
        string|int|float|BigDecimal $discountPercent = 0,
        bool $roundTotal = false,
        bool $vatPayer = false,
    ): DocumentTotals {
        $discount = BigDecimal::of($discountPercent);
        $this->assertPercent($discount, 'Sleva na doklad');

        $zero = BigDecimal::zero()->toScale(self::SCALE);

        $lineTotals = [];
        $subtotal = $zero;
        /** @var array<string, array{rate: BigDecimal, base: BigDecimal}> $groups */
        $groups = [];

        foreach ($lines as $line) {
            $lineTotal = $this->lineTotal($line);
            $lineTotals[] = $lineTotal;
            $subtotal = $subtotal->plus($lineTotal);

            $key = (string) $line->vatRate->toScale(self::SCALE, RoundingMode::HalfUp);
            $groups[$key] ??= ['rate' => $line->vatRate, 'base' => $zero];
            $groups[$key]['base'] = $groups[$key]['base']->plus($lineTotal);
        }

        $discountTotal = $this->percentOf($subtotal, $discount);
        $net = $subtotal->minus($discountTotal);

        $vatTotal = $zero;
        $vatSummary = [];
        if ($vatPayer) {
            $vatSummary = $this->vatSummary($groups, $discount, $net);
            foreach ($vatSummary as $row) {
                $vatTotal = $vatTotal->plus($row->vat);
            }
        }

        $beforeRounding = $net->plus($vatTotal);
        $rounding = $zero;
        if ($roundTotal) {
            $rounded = $beforeRounding->toScale(0, RoundingMode::HalfUp)->toScale(self::SCALE);
            $rounding = $rounded->minus($beforeRounding);
        }

        return new DocumentTotals(
            lineTotals: $lineTotals,
            subtotal: $subtotal,
            discountTotal: $discountTotal,
            vatTotal: $vatTotal,
            rounding: $rounding,
            total: $beforeRounding->plus($rounding),
            vatSummary: $vatSummary,
        );
    }

    public function lineTotal(CalculatorLine $line): BigDecimal
    {
        $this->assertPercent($line->discountPercent, 'Sleva na položku');

        return $line->qty
            ->multipliedBy($line->unitPrice)
            ->multipliedBy(BigDecimal::of(100)->minus($line->discountPercent))
            ->withPointMovedLeft(2)
            ->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    /**
     * DPH po sazbách. Sleva na doklad se rozdělí poměrně mezi sazby, případný haléřový
     * rozdíl po zaokrouhlení se doúčtuje do největšího základu, aby součet seděl.
     *
     * @param  array<string, array{rate: BigDecimal, base: BigDecimal}>  $groups
     * @return list<VatSummaryRow>
     */
    private function vatSummary(array $groups, BigDecimal $discount, BigDecimal $net): array
    {
        $keep = BigDecimal::of(100)->minus($discount);

        $bases = [];
        foreach ($groups as $key => $group) {
            $bases[$key] = $group['base']
                ->multipliedBy($keep)
                ->withPointMovedLeft(2)
                ->toScale(self::SCALE, RoundingMode::HalfUp);
        }

        if ($bases === []) {
            return [];
        }

        $difference = $net;
        foreach ($bases as $base) {
            $difference = $difference->minus($base);
        }

        if (! $difference->isZero()) {
            $largest = array_keys($bases, max($bases), false)[0];
            $bases[$largest] = $bases[$largest]->plus($difference);
        }

        $rows = [];
        foreach ($groups as $key => $group) {
            $rows[] = new VatSummaryRow(
                rate: $group['rate'],
                base: $bases[$key],
                vat: $this->percentOf($bases[$key], $group['rate']),
            );
        }

        usort($rows, fn (VatSummaryRow $a, VatSummaryRow $b): int => $b->rate->compareTo($a->rate));

        return $rows;
    }

    private function percentOf(BigDecimal $amount, BigDecimal $percent): BigDecimal
    {
        return $amount
            ->multipliedBy($percent)
            ->withPointMovedLeft(2)
            ->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    private function assertPercent(BigDecimal $percent, string $label): void
    {
        if ($percent->isLessThan(0) || $percent->isGreaterThan(100)) {
            throw new InvalidArgumentException("{$label} musí být v rozmezí 0 až 100 %.");
        }
    }
}
