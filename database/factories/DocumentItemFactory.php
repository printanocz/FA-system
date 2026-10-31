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
        static $sequence = 0;
        $n = ++$sequence;

        return [
            'document_id' => Document::factory(),
            'position' => 1,
            'name' => "Položka {$n}",
            'qty' => '1.0000',
            'unit' => 'ks',
            'unit_price' => '100.00',
            'discount_percent' => '0.00',
            'vat_rate' => '0.00',
            'line_total' => '100.00',
        ];
    }
}
