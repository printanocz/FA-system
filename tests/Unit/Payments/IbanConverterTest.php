<?php

use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use App\Domain\Payments\IbanConverter;

beforeEach(fn () => $this->iban = new IbanConverter);

it('converts known Czech accounts to IBAN', function (string $account, string $bank, string $expected) {
    expect($this->iban->fromCzechAccount($account, $bank))->toBe($expected);
})->with([
    'no prefix' => ['2401234567', '0800', 'CZ1508000000002401234567'],
    'with prefix' => ['19-2000145399', '0800', 'CZ6508000000192000145399'],
    'short number' => ['123456789', '0100', 'CZ1801000000000123456789'],
]);

it('pretty prints an IBAN in groups of four', function () {
    expect($this->iban->pretty('cz1508000000002401234567'))->toBe('CZ15 0800 0000 0024 0123 4567');
});

it('validates generated IBANs with mod 97', function () {
    expect($this->iban->isValid('CZ15 0800 0000 0024 0123 4567'))->toBeTrue()
        ->and($this->iban->isValid('cz6508000000192000145399'))->toBeTrue();
});

it('validates IBANs of other countries', function () {
    expect($this->iban->isValid('DE89 3704 0044 0532 0130 00'))->toBeTrue()
        ->and($this->iban->isValid('SK31 1200 0000 1987 4263 7541'))->toBeTrue();
});

it('rejects IBANs with a wrong check digit or shape', function (string $iban) {
    expect($this->iban->isValid($iban))->toBeFalse();
})->with(['CZ16 0800 0000 0024 0123 4567', 'CZ15 0800 0000 0024 0123 456', '1508000000002401234567', '', 'CZ15-0800']);

it('rejects malformed account numbers and bank codes', function (string $account, string $bank) {
    $this->iban->fromCzechAccount($account, $bank);
})->with([
    'letters' => ['24012A4567', '0800'],
    'too long' => ['12345678901', '0800'],
    'long prefix' => ['1234567-123456', '0800'],
    'empty' => ['', '0800'],
    'short bank' => ['2401234567', '800'],
    'letters in bank' => ['2401234567', '08A0'],
])->throws(InvalidPaymentDataException::class);

it('ignores spaces in the account number', function () {
    expect($this->iban->fromCzechAccount(' 19 - 2000145399 ', ' 0800 '))->toBe('CZ6508000000192000145399');
});
