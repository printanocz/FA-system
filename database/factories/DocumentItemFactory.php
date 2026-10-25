<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentItem> */
class DocumentItemFactory extends Factory
{
    protected $model = DocumentItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'position' => 1,
            'name' => fake()->words(3, true),
            'qty' => '1.0000',
            'unit' => 'ks',
            'unit_price' => '100.00',
            'discount_percent' => '0.00',
            'vat_rate' => '0.00',
            'line_total' => '100.00',
        ];
    }
}
