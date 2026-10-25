<?php

namespace App\Actions\Settings;

use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use App\Domain\Payments\IbanConverter;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Založí nebo upraví bankovní účet. IBAN se dopočítá z čísla účtu a kódu banky;
 * ručně zadaný IBAN (např. zahraniční účet) se jen zkontroluje. Výchozí účet je vždy právě jeden.
 */
final class SaveBankAccount
{
    public function __construct(private readonly IbanConverter $ibans) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(array $input, ?BankAccount $account = null): BankAccount
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value) === '' ? null : trim($value);
            }
        }
        $input['currency'] = strtoupper((string) ($input['currency'] ?? 'CZK'));

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:30'],
            'bank_code' => ['required', 'digits:4'],
            'iban' => ['nullable', 'string', 'max:40'],
            'swift' => ['nullable', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
            'currency' => ['required', 'alpha', 'size:3'],
            'is_default' => ['sometimes', 'boolean'],
        ], attributes: [
            'name' => 'název',
            'account_number' => 'číslo účtu',
            'bank_code' => 'kód banky',
            'swift' => 'SWIFT',
            'currency' => 'měna',
        ])->validate();

        $data['iban'] = $this->resolveIban($data);
        $data['swift'] = isset($data['swift']) ? strtoupper($data['swift']) : null;
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        return DB::transaction(function () use ($data, $account): BankAccount {
            $account ??= new BankAccount;
            $account->fill($data)->save();

            // Vždy musí existovat výchozí účet (první účet, nebo zrušený příznak u jediného výchozího).
            if (! BankAccount::query()->where('is_default', true)->exists()) {
                $account->forceFill(['is_default' => true])->save();
            }

            return $account;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function resolveIban(array $data): string
    {
        if (isset($data['iban'])) {
            if (! $this->ibans->isValid((string) $data['iban'])) {
                throw ValidationException::withMessages(['iban' => 'IBAN není platný.']);
            }

            return $this->ibans->normalize((string) $data['iban']);
        }

        try {
            return $this->ibans->fromCzechAccount((string) $data['account_number'], (string) $data['bank_code']);
        } catch (InvalidPaymentDataException $e) {
            throw ValidationException::withMessages(['account_number' => $e->getMessage()]);
        }
    }
}
