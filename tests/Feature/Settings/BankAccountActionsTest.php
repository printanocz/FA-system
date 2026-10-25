<?php

use App\Actions\Settings\DeleteBankAccount;
use App\Actions\Settings\SaveBankAccount;
use App\Models\BankAccount;
use Illuminate\Validation\ValidationException;

function accountInput(array $overrides = []): array
{
    return array_merge([
        'name' => 'Česká spořitelna',
        'account_number' => '2401234567',
        'bank_code' => '0800',
        'currency' => 'CZK',
    ], $overrides);
}

beforeEach(function () {
    $this->save = app(SaveBankAccount::class);
    $this->delete = app(DeleteBankAccount::class);
});

it('creates an account and computes the IBAN', function () {
    $account = ($this->save)(accountInput());

    expect($account->iban)->toBe('CZ1508000000002401234567')
        ->and($account->currency)->toBe('CZK');
});

it('computes the IBAN of an account with a prefix', function () {
    expect(($this->save)(accountInput(['account_number' => '19-2000145399']))->iban)->toBe('CZ6508000000192000145399');
});

it('makes the first account the default', function () {
    expect(($this->save)(accountInput())->is_default)->toBeTrue();
});

it('keeps exactly one default account', function () {
    $first = ($this->save)(accountInput());
    $second = ($this->save)(accountInput(['account_number' => '19-2000145399', 'is_default' => true]));
    $third = ($this->save)(accountInput(['account_number' => '123456789', 'bank_code' => '0100']));

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and($third->fresh()->is_default)->toBeFalse()
        ->and(BankAccount::query()->where('is_default', true)->count())->toBe(1);
});

it('does not let the only default account lose its flag', function () {
    $account = ($this->save)(accountInput());

    ($this->save)(accountInput(['is_default' => false]), $account);

    expect($account->fresh()->is_default)->toBeTrue();
});

it('recomputes the IBAN when the account number changes', function () {
    $account = ($this->save)(accountInput());

    ($this->save)(accountInput(['account_number' => '19-2000145399']), $account);

    expect($account->fresh()->iban)->toBe('CZ6508000000192000145399')->and(BankAccount::query()->count())->toBe(1);
});

it('accepts a valid manual IBAN and normalizes it', function () {
    $account = ($this->save)(accountInput(['account_number' => '1', 'bank_code' => '0000', 'iban' => 'de89 3704 0044 0532 0130 00', 'currency' => 'eur']));

    expect($account->iban)->toBe('DE89370400440532013000')->and($account->currency)->toBe('EUR');
});

it('uppercases the SWIFT code', function () {
    expect(($this->save)(accountInput(['swift' => 'gibaczpx']))->swift)->toBe('GIBACZPX');
});

it('rejects invalid input', function (array $override, string $field) {
    try {
        ($this->save)(accountInput($override));
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field)->and(BankAccount::query()->count())->toBe(0);

        return;
    }

    $this->fail('Validation did not fail.');
})->with([
    'no name' => [['name' => ''], 'name'],
    'letters in account' => [['account_number' => '24012A4567'], 'account_number'],
    'long prefix' => [['account_number' => '1234567-123456'], 'account_number'],
    'short bank code' => [['bank_code' => '800'], 'bank_code'],
    'letters in bank code' => [['bank_code' => '08A0'], 'bank_code'],
    'bad currency' => [['currency' => 'KORUNY'], 'currency'],
    'bad IBAN' => [['iban' => 'CZ00 1234'], 'iban'],
    'bad SWIFT' => [['swift' => 'XX'], 'swift'],
]);

it('promotes the oldest remaining account when the default is deleted', function () {
    $first = ($this->save)(accountInput());
    $second = ($this->save)(accountInput(['account_number' => '19-2000145399', 'is_default' => true]));
    $third = ($this->save)(accountInput(['account_number' => '123456789', 'bank_code' => '0100']));

    ($this->delete)($second);

    expect(BankAccount::query()->count())->toBe(2)
        ->and($first->fresh()->is_default)->toBeTrue()
        ->and($third->fresh()->is_default)->toBeFalse();
});

it('deletes a non-default account without touching the default', function () {
    $first = ($this->save)(accountInput());
    $second = ($this->save)(accountInput(['account_number' => '19-2000145399']));

    ($this->delete)($second);

    expect($first->fresh()->is_default)->toBeTrue()->and(BankAccount::query()->count())->toBe(1);
});

it('can delete the last account', function () {
    ($this->delete)(($this->save)(accountInput()));

    expect(BankAccount::query()->count())->toBe(0);
});

it('leaves issued documents with their snapshot when the account is deleted', function () {
    $account = ($this->save)(accountInput());
    $issued = issueInvoice(draftInvoice());
    $snapshot = $issued->fresh()->bank_account_snapshot;

    ($this->delete)(BankAccount::query()->findOrFail($issued->bank_account_id));

    expect($issued->fresh()->bank_account_snapshot)->toBe($snapshot)->and($account)->not->toBeNull();
});
