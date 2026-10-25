<?php

namespace Database\Factories;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\PaymentMethod;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => DocumentType::Invoice,
            'status' => DocumentStatus::Draft,
            'contact_id' => Contact::factory(),
            'currency' => 'CZK',
            'language' => 'cs',
            'payment_method' => PaymentMethod::BankTransfer,
            'ks' => '0308',
            'discount_percent' => '0.00',
            'round_total' => false,
        ];
    }

    public function quote(): static
    {
        return $this->state(['type' => DocumentType::Quote]);
    }
}
