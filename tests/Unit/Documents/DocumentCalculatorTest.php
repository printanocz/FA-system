<?php

use App\Domain\Documents\CalculatorLine;
use App\Domain\Documents\DocumentCalculator;
use Brick\Math\BigDecimal;

function calc(array $lines, string|int $discount = 0, bool $round = false, bool $vatPayer = false)
{
    return (new DocumentCalculator)->calculate($lines, $discount, $round, $vatPayer);
}

it('calculates an invoice of 38 hours at 400 Kč as 15 200,00 Kč', function () {
    $totals = calc([new CalculatorLine(38, 400)]);

    expect((string) $totals->lineTotals[0])->toBe('15200.00')
        ->and((string) $totals->subtotal)->toBe('15200.00')
        ->and((string) $totals->vatTotal)->toBe('0.00')
        ->and((string) $totals->total)->toBe('15200.00');
});

it('sums several items', function () {
    $totals = calc([
        new CalculatorLine('2', '49.90'),
        new CalculatorLine('1.25', '80'),
        new CalculatorLine(10, '3.33'),
    ]);

    expect(array_map('strval', $totals->lineTotals))->toBe(['99.80', '100.00', '33.30'])
        ->and((string) $totals->subtotal)->toBe('233.10')
        ->and((string) $totals->total)->toBe('233.10');
});

it('does not lose pennies the way floats do', function () {
    $totals = calc([new CalculatorLine(3, '0.10'), new CalculatorLine(1, '0.20')]);

    expect((string) $totals->total)->toBe('0.50');
});

it('applies a discount to a line', function () {
    $totals = calc([new CalculatorLine(3, 100, 15), new CalculatorLine(10, '3.33', 10)]);

    expect(array_map('strval', $totals->lineTotals))->toBe(['255.00', '29.97'])
        ->and((string) $totals->subtotal)->toBe('284.97');
});

it('applies a discount to the whole document', function () {
    $totals = calc([new CalculatorLine(1, '1000')], '33.33');

    expect((string) $totals->discountTotal)->toBe('333.30')
        ->and((string) $totals->total)->toBe('666.70');
});

it('combines line discounts and a document discount', function () {
    $totals = calc([
        new CalculatorLine('2', '49.90'),
        new CalculatorLine('1.25', '80'),
        new CalculatorLine(10, '3.33', 10),
    ], 5);

    expect((string) $totals->subtotal)->toBe('229.77')
        ->and((string) $totals->discountTotal)->toBe('11.49')
        ->and((string) $totals->total)->toBe('218.28');
});

it('allows a 100 % discount', function () {
    $totals = calc([new CalculatorLine(2, 50)], 100);

    expect((string) $totals->discountTotal)->toBe('100.00')
        ->and((string) $totals->total)->toBe('0.00');
});

it('rejects a discount outside 0 to 100 %', function (string|int $percent) {
    calc([new CalculatorLine(1, 100)], $percent);
})->with([-1, '100.01'])->throws(InvalidArgumentException::class);

it('rejects a line discount outside 0 to 100 %', function () {
    calc([new CalculatorLine(1, 100, 101)]);
})->throws(InvalidArgumentException::class);

it('returns zeros for a document without items', function () {
    $totals = calc([]);

    expect($totals->lineTotals)->toBe([])
        ->and((string) $totals->subtotal)->toBe('0.00')
        ->and((string) $totals->total)->toBe('0.00');
});

describe('penny boundaries (half up)', function () {
    it('rounds 0,005 up to 0,01', function () {
        expect((string) calc([new CalculatorLine('0.0050', 1)])->total)->toBe('0.01')
            ->and((string) calc([new CalculatorLine('0.5', '0.01')])->total)->toBe('0.01');
    });

    it('rounds 0,0049 down to 0,00', function () {
        expect((string) calc([new CalculatorLine('0.0049', 1)])->total)->toBe('0.00');
    });

    it('rounds 1,5 × 99,99 = 149,985 up to 149,99', function () {
        expect((string) calc([new CalculatorLine('1.5', '99.99')])->total)->toBe('149.99');
    });

    it('rounds a tie away from zero for negative amounts too', function () {
        expect((string) calc([new CalculatorLine('-0.0050', 1)])->total)->toBe('-0.01');
    });

    it('rounds a document discount of 0,005 up', function () {
        $totals = calc([new CalculatorLine(1, '0.50')], 1);

        expect((string) $totals->discountTotal)->toBe('0.01')
            ->and((string) $totals->total)->toBe('0.49');
    });
});

describe('rounding of the total to whole crowns', function () {
    it('rounds down below .50', function () {
        $totals = calc([new CalculatorLine(1, '15200.49')], round: true);

        expect((string) $totals->rounding)->toBe('-0.49')
            ->and((string) $totals->total)->toBe('15200.00');
    });

    it('rounds up from .50', function () {
        $totals = calc([new CalculatorLine(1, '15200.50')], round: true);

        expect((string) $totals->rounding)->toBe('0.50')
            ->and((string) $totals->total)->toBe('15201.00');
    });

    it('leaves a whole amount alone', function () {
        $totals = calc([new CalculatorLine(38, 400)], round: true);

        expect((string) $totals->rounding)->toBe('0.00')
            ->and((string) $totals->total)->toBe('15200.00');
    });

    it('is off unless enabled', function () {
        $totals = calc([new CalculatorLine(1, '15200.50')]);

        expect((string) $totals->rounding)->toBe('0.00')
            ->and((string) $totals->total)->toBe('15200.50');
    });

    it('rounds the total after the document discount', function () {
        $totals = calc([
            new CalculatorLine('2', '49.90'),
            new CalculatorLine('1.25', '80'),
            new CalculatorLine(10, '3.33', 10),
        ], 5, true);

        expect((string) $totals->rounding)->toBe('-0.28')
            ->and((string) $totals->total)->toBe('218.00');
    });
});

describe('large amounts', function () {
    it('keeps exact cents near the DECIMAL(15,2) limit', function () {
        $totals = calc([new CalculatorLine(1_000_000, '9999999.99')]);

        expect((string) $totals->total)->toBe('9999999990000.00');
    });

    it('stays exact for many cents', function () {
        $totals = calc([
            new CalculatorLine('999999999', '9999.99'),
            new CalculatorLine('0.0001', '9999999999999.99'),
        ]);

        expect(array_map('strval', $totals->lineTotals))->toBe(['9999989990000.01', '1000000000.00'])
            ->and((string) $totals->total)->toBe('10000989990000.01');
    });
});

describe('VAT', function () {
    it('is zero for a non-payer whatever the item rates say', function () {
        $totals = calc([new CalculatorLine(1, 100, 0, 21)], vatPayer: false);

        expect((string) $totals->vatTotal)->toBe('0.00')
            ->and($totals->vatSummary)->toBe([])
            ->and((string) $totals->total)->toBe('100.00');
    });

    it('is added on top of net prices for a payer', function () {
        $totals = calc([new CalculatorLine(1, 100, 0, 21)], vatPayer: true);

        expect((string) $totals->vatTotal)->toBe('21.00')
            ->and((string) $totals->total)->toBe('121.00');
    });

    it('is computed per rate from the base after the document discount', function () {
        $totals = calc([
            new CalculatorLine(1, 100, 0, 21),
            new CalculatorLine(1, 100, 0, 12),
            new CalculatorLine(1, 50, 0, 0),
        ], 10, vatPayer: true);

        $summary = array_map(
            fn ($row) => [(string) $row->rate, (string) $row->base, (string) $row->vat],
            $totals->vatSummary,
        );

        expect($summary)->toBe([
            ['21.00', '90.00', '18.90'],
            ['12.00', '90.00', '10.80'],
            ['0.00', '45.00', '0.00'],
        ])
            ->and((string) $totals->vatTotal)->toBe('29.70')
            ->and((string) $totals->total)->toBe('254.70');
    });

    it('keeps the bases equal to the discounted net after rounding', function () {
        $totals = calc([
            new CalculatorLine(1, '0.01', 0, 21),
            new CalculatorLine(1, '0.01', 0, 15),
        ], 50, vatPayer: true);

        $bases = array_reduce(
            $totals->vatSummary,
            fn (BigDecimal $sum, $row) => $sum->plus($row->base),
            BigDecimal::zero(),
        );

        expect((string) $bases)->toBe('0.01');
    });

    it('rounds the gross total of a payer to whole crowns', function () {
        $totals = calc([new CalculatorLine(1, '99.99', 0, 21)], round: true, vatPayer: true);

        expect((string) $totals->vatTotal)->toBe('21.00')
            ->and((string) $totals->rounding)->toBe('0.01')
            ->and((string) $totals->total)->toBe('121.00');
    });
});

it('always satisfies total = subtotal − discount + VAT + rounding', function () {
    mt_srand(7);

    for ($i = 0; $i < 200; $i++) {
        $lines = [];
        foreach (range(1, mt_rand(1, 6)) as $_) {
            $lines[] = new CalculatorLine(
                mt_rand(1, 99999) / 10000,
                mt_rand(0, 9999999) / 100,
                mt_rand(0, 3000) / 100,
                [0, 12, 21][mt_rand(0, 2)],
            );
        }

        $totals = calc($lines, mt_rand(0, 5000) / 100, (bool) mt_rand(0, 1), (bool) mt_rand(0, 1));

        $expected = $totals->subtotal->minus($totals->discountTotal)->plus($totals->vatTotal)->plus($totals->rounding);

        expect($totals->total->isEqualTo($expected))->toBeTrue()
            ->and($totals->total->getScale())->toBe(2);
    }
});

it('exposes the values for the documents columns', function () {
    $columns = calc([new CalculatorLine(1, '15200.40')], round: true)->toColumns();

    expect($columns)->toBe([
        'subtotal' => '15200.40',
        'vat_total' => '0.00',
        'rounding' => '-0.40',
        'total' => '15200.00',
    ]);
});

it('converts the total to a brick/money value', function () {
    $money = calc([new CalculatorLine(38, 400)])->totalAsMoney('CZK');

    expect($money->getCurrency()->getCurrencyCode())->toBe('CZK')
        ->and((string) $money->getAmount())->toBe('15200.00');
});
