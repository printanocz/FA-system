<?php

use App\Domain\Documents\Exceptions\NumberSeriesException;
use App\Domain\Documents\NumberSeriesService;
use App\Models\NumberSeries;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->service = new NumberSeriesService);

it('allocates consecutive numbers in the format {YYYY}{NNN}', function () {
    $series = NumberSeries::factory()->create(['next_number' => 61]);
    $date = Carbon::parse('2026-03-10');

    expect($this->service->allocate($series, $date)->number)->toBe('2026061')
        ->and($this->service->allocate($series, $date)->number)->toBe('2026062')
        ->and($series->fresh()->next_number)->toBe(63);
});

it('returns the year and sequence of the allocation', function () {
    $series = NumberSeries::factory()->create();
    $allocated = $this->service->allocate($series, Carbon::parse('2026-03-10'));

    expect($allocated->year)->toBe(2026)->and($allocated->sequence)->toBe(1);
});

it('restarts the sequence in a new year', function () {
    $series = NumberSeries::factory()->create(['next_number' => 40]);

    $this->service->allocate($series, Carbon::parse('2026-12-31'));

    expect($this->service->allocate($series, Carbon::parse('2027-01-01'))->number)->toBe('2027001')
        ->and($this->service->allocate($series, Carbon::parse('2027-01-02'))->number)->toBe('2027002');
});

it('keeps counting across years when yearly reset is off', function () {
    $series = NumberSeries::factory()->create(['reset_yearly' => false, 'next_number' => 5]);

    $this->service->allocate($series, Carbon::parse('2026-12-31'));

    expect($this->service->allocate($series, Carbon::parse('2027-01-01'))->number)->toBe('2027006');
});

it('refuses an issue date from a year already passed in the series', function () {
    $series = NumberSeries::factory()->create();
    $this->service->allocate($series, Carbon::parse('2027-01-05'));

    $this->service->allocate($series, Carbon::parse('2026-12-31'));
})->throws(NumberSeriesException::class);

it('previews the next number without changing the series', function () {
    $series = NumberSeries::factory()->create(['next_number' => 7]);

    expect($this->service->preview($series, Carbon::parse('2026-05-01')))->toBe('2026007')
        ->and($series->fresh()->next_number)->toBe(7);
});

it('previews 001 for a new year', function () {
    $series = NumberSeries::factory()->create(['next_number' => 12]);
    $this->service->allocate($series, Carbon::parse('2026-05-01'));

    expect($this->service->preview($series->fresh(), Carbon::parse('2027-01-01')))->toBe('2027001');
});

it('releases the last allocated number back to the series', function () {
    $series = NumberSeries::factory()->create();
    $allocated = $this->service->allocate($series, Carbon::parse('2026-05-01'));

    $this->service->release($series, $allocated->year, $allocated->sequence);

    expect($series->fresh()->next_number)->toBe(1);
});

it('does not move the counter back when the released number is not the latest', function () {
    $series = NumberSeries::factory()->create();
    $first = $this->service->allocate($series, Carbon::parse('2026-05-01'));
    $this->service->allocate($series, Carbon::parse('2026-05-02'));

    $this->service->release($series, $first->year, $first->sequence);

    expect($series->fresh()->next_number)->toBe(3);
});

it('leaves the counter untouched when the surrounding transaction rolls back', function () {
    $series = NumberSeries::factory()->create();

    try {
        DB::transaction(function () use ($series) {
            $this->service->allocate($series, Carbon::parse('2026-05-01'));

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect($series->fresh()->next_number)->toBe(1)
        ->and($this->service->allocate($series, Carbon::parse('2026-05-01'))->number)->toBe('2026001');
});
