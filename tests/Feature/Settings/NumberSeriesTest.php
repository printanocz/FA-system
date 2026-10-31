<?php

use App\Actions\Settings\UpdateNumberSeries;
use App\Domain\Documents\Exceptions\NumberSeriesException;
use App\Domain\Documents\NumberSeriesService;
use App\Models\NumberSeries;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->update = app(UpdateNumberSeries::class);
    $this->series = NumberSeries::factory()->create();
});

it('continues from iDoklad: a series set to 61 issues 2026061', function () {
    ($this->update)($this->series, ['next_number' => 61]);

    expect(issueInvoice(draftInvoice(), '2026-03-10')->number)->toBe('2026061')
        ->and(issueInvoice(draftInvoice(), '2026-03-11')->number)->toBe('2026062');
});

it('renames the series', function () {
    ($this->update)($this->series, ['name' => 'Faktury 2026']);

    expect($this->series->fresh()->name)->toBe('Faktury 2026');
});

it('requires a name', function () {
    ($this->update)($this->series, ['name' => '']);
})->throws(ValidationException::class);

it('changes the prefix and the format', function () {
    ($this->update)($this->series, ['prefix' => 'FA', 'format' => '{YY}{NNNN}']);

    expect(issueInvoice(draftInvoice(), '2026-03-10')->number)->toBe('FA260001');
});

it('changes the yearly reset and the default flag', function () {
    $other = NumberSeries::factory()->create();

    ($this->update)($this->series, ['reset_yearly' => false, 'is_default' => true]);

    expect($this->series->fresh()->reset_yearly)->toBeFalse()
        ->and($this->series->fresh()->is_default)->toBeTrue()
        ->and($other->fresh()->is_default)->toBeFalse();
});

it('updates the passed model', function () {
    ($this->update)($this->series, ['next_number' => 61]);

    expect($this->series->next_number)->toBe(61);
});

it('lets the next number go forward after numbers were issued', function () {
    issueInvoice(draftInvoice(), '2026-03-10');

    ($this->update)($this->series, ['next_number' => 100]);

    expect(issueInvoice(draftInvoice(), '2026-03-11')->number)->toBe('2026100');
});

it('never lets the next number go below an already allocated number', function () {
    ($this->update)($this->series, ['next_number' => 61]);
    issueInvoice(draftInvoice(), '2026-03-10');

    ($this->update)($this->series, ['next_number' => 61]);
})->throws(NumberSeriesException::class);

it('refuses to go back to a number that was issued', function () {
    issueInvoice(draftInvoice(), '2026-03-10');
    issueInvoice(draftInvoice(), '2026-03-11');

    try {
        ($this->update)($this->series, ['next_number' => 1]);
    } catch (NumberSeriesException) {
        expect($this->series->fresh()->next_number)->toBe(3)
            ->and(issueInvoice(draftInvoice(), '2026-03-12')->number)->toBe('2026003');

        return;
    }

    $this->fail('Going back was allowed.');
});

it('allows setting any number while the series has no numbered document', function () {
    ($this->update)($this->series, ['next_number' => 500]);
    ($this->update)($this->series, ['next_number' => 3]);

    expect($this->series->fresh()->next_number)->toBe(3);
});

it('allows going back after the only numbered document was deleted', function () {
    $issued = issueInvoice(draftInvoice(), '2026-03-10');
    $issued->delete();

    ($this->update)($this->series, ['next_number' => 1]);

    expect($this->series->fresh()->next_number)->toBe(1);
});

it('rejects invalid input', function (array $input, string $field) {
    try {
        ($this->update)($this->series, $input);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);

        return;
    }

    $this->fail('Validation did not fail.');
})->with([
    'zero' => [['next_number' => 0], 'next_number'],
    'text' => [['next_number' => 'abc'], 'next_number'],
    'format without sequence' => [['format' => '{YYYY}'], 'format'],
    'format with two sequences' => [['format' => '{NNN}{NN}'], 'format'],
    'empty format' => [['format' => ''], 'format'],
    'bad prefix' => [['prefix' => 'F A*'], 'prefix'],
    'long prefix' => [['prefix' => str_repeat('A', 21)], 'prefix'],
]);

it('leaves the series untouched when validation fails', function () {
    try {
        ($this->update)($this->series, ['prefix' => 'FA', 'next_number' => 0]);
    } catch (ValidationException) {
    }

    expect($this->series->fresh()->prefix)->toBe('')->and($this->series->fresh()->next_number)->toBe(1);
});

it('still resets the sequence in a new year after the number was moved forward', function () {
    ($this->update)($this->series, ['next_number' => 61]);
    app(NumberSeriesService::class)->allocate($this->series, Carbon::parse('2026-12-31'));

    expect(app(NumberSeriesService::class)->allocate($this->series, Carbon::parse('2027-01-02'))->number)->toBe('2027001');
});
