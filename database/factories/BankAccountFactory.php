<?php

namespace Database\Factories;

use App\Domain\Payments\IbanConverter;
use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BankAccount> */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Česká spořitelna',
            'account_number' => '1234567890',
            'bank_code' => '0800',
            // Platný IBAN dopočítaný z čísla účtu a kódu banky (i když test číslo přepíše).
            'iban' => fn (array $attributes): string => (new IbanConverter)->fromCzechAccount(
                (string) $attributes['account_number'],
                (string) $attributes['bank_code'],
            ),
            'swift' => 'GIBACZPX',
            'currency' => 'CZK',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
