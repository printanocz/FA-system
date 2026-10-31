<?php

use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use App\Domain\Payments\SpaydFormatter;

const SPAYD_IBAN = 'CZ1508000000002401234567';

beforeEach(fn () => $this->spayd = new SpaydFormatter);

it('builds a full payment string in the order of the specification', function () {
    $text = $this->spayd->format(
        iban: SPAYD_IBAN,
        amount: '15200',
        currency: 'czk',
        variableSymbol: '2026001',
        constantSymbol: '0308',
        dueDate: new DateTimeImmutable('2026-11-08'),
        message: 'Faktura 2026001',
    );

    expect($text)->toBe('SPD*1.0*ACC:CZ1508000000002401234567*AM:15200.00*CC:CZK*X-VS:2026001*X-KS:0308*DT:20261108*MSG:FAKTURA 2026001');
});

it('adds the BIC after the IBAN with a plus sign', function () {
    $text = $this->spayd->format(SPAYD_IBAN, '1', bic: 'gibaczpx');

    expect($text)->toStartWith('SPD*1.0*ACC:CZ1508000000002401234567+GIBACZPX*AM:1.00*');
});

it('omits optional fields that are not given', function () {
    expect($this->spayd->format(SPAYD_IBAN, '100.5'))->toBe('SPD*1.0*ACC:CZ1508000000002401234567*AM:100.50*CC:CZK');
});

it('formats amounts with two decimals and a dot', function (string|int $amount, string $expected) {
    expect($this->spayd->format(SPAYD_IBAN, $amount))->toContain("*AM:{$expected}*");
})->with([[0, '0.00'], [1, '1.00'], ['0.5', '0.50'], ['1234.56', '1234.56'], ['9999999.99', '9999999.99']]);

it('rejects amounts that cannot be written', function (string $amount) {
    $this->spayd->format(SPAYD_IBAN, $amount);
})->with(['-1', '1.005', 'abc', '10000000'])->throws(InvalidPaymentDataException::class);

it('escapes the star in the message', function () {
    $text = $this->spayd->format(SPAYD_IBAN, '1', message: 'A*B');

    expect($text)->toEndWith('*MSG:A%2AB')->and(substr_count($text, '*'))->toBe(5);
});

it('escapes the percent sign so it cannot be read as an escape', function () {
    expect($this->spayd->format(SPAYD_IBAN, '1', message: 'SLEVA 10%'))->toEndWith('*MSG:SLEVA 10%25');
});

it('strips diacritics and upper-cases the message', function () {
    expect($this->spayd->message('Žluťoučký kůň úpěl ďábelské ódy'))->toBe('ZLUTOUCKY KUN UPEL DABELSKE ODY');
});

it('cuts the message to 60 characters', function () {
    $message = $this->spayd->message(str_repeat('a', 80));

    expect($message)->toBe(str_repeat('A', 60));
});

it('counts the 60 characters before escaping so an escape is never cut', function () {
    $text = $this->spayd->format(SPAYD_IBAN, '1', message: str_repeat('*', 70));

    expect($text)->toEndWith('*MSG:'.str_repeat('%2A', 60));
});

it('drops control characters and collapses whitespace', function () {
    expect($this->spayd->message("A\tB\n  C\x00D"))->toBe('A B CD');
});

it('leaves out an empty message', function () {
    expect($this->spayd->format(SPAYD_IBAN, '1', message: '   '))->not->toContain('MSG');
});

it('rejects an invalid IBAN, BIC, currency and symbols', function (array $arguments) {
    $this->spayd->format(...array_merge(['iban' => SPAYD_IBAN, 'amount' => '1'], $arguments));
})->with([
    'iban' => [['iban' => 'CZ00 0000']],
    'bic' => [['bic' => 'XX']],
    'currency' => [['currency' => 'KORUNY']],
    'vs letters' => [['variableSymbol' => 'FA1']],
    'vs too long' => [['variableSymbol' => '12345678901']],
    'ks letters' => [['constantSymbol' => 'KS']],
])->throws(InvalidPaymentDataException::class);
