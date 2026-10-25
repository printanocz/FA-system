<?php

use App\Domain\Documents\Exceptions\NumberSeriesException;
use App\Domain\Documents\NumberSeriesService;

function numberFormat(string $prefix, string $format, int $year, int $sequence): string
{
    return (new NumberSeriesService)->format($prefix, $format, $year, $sequence);
}

it('renders the default format {YYYY}{NNN} as 2026061', function () {
    expect(numberFormat('', '{YYYY}{NNN}', 2026, 61))->toBe('2026061');
});

it('pads the sequence to the number of N', function () {
    expect(numberFormat('', '{YYYY}{NNN}', 2026, 1))->toBe('2026001')
        ->and(numberFormat('', '{YYYY}{NNNNN}', 2026, 7))->toBe('202600007');
});

it('grows past the padding instead of truncating', function () {
    expect(numberFormat('', '{YYYY}{NNN}', 2026, 1234))->toBe('20261234');
});

it('supports a prefix and a two-digit year', function () {
    expect(numberFormat('ZF', '{YY}{NNNN}', 2026, 7))->toBe('ZF260007');
});

it('rejects a format without the sequence token', function () {
    numberFormat('', '{YYYY}', 2026, 1);
})->throws(NumberSeriesException::class);

it('rejects a format with two sequence tokens', function () {
    numberFormat('', '{NNN}{NN}', 2026, 1);
})->throws(NumberSeriesException::class);
