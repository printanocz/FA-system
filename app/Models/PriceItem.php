<?php

namespace App\Models;

use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceItem extends Model
{
    /** @use HasFactory<PriceItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
        ];
    }
}
