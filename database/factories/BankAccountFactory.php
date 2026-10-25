<?php

namespace Database\Factories;

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
            'iban' => 'CZ6508000000001234567890',
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
