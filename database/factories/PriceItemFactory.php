<?php

namespace Database\Factories;

use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceItem> */
class PriceItemFactory extends Factory
{
    protected $model = PriceItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $sequence = 0;
        $n = ++$sequence;

        return [
            'name' => "Položka {$n}",
            'code' => null,
            'unit' => 'hod',
            'unit_price' => '400.00',
            'currency' => 'CZK',
            'vat_rate' => '0.00',
        ];
    }
}
