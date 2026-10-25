<?php

namespace Database\Factories;

use App\Domain\Documents\PaymentMethod;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'amount' => '100.00',
            'currency' => 'CZK',
            'paid_at' => now()->toDateString(),
            'method' => PaymentMethod::BankTransfer,
        ];
    }
}
