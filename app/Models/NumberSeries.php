<?php

namespace App\Models;

use App\Domain\Documents\DocumentType;
use Database\Factories\NumberSeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property DocumentType $doc_type
 * @property int $next_number
 * @property int|null $last_year
 * @property bool $reset_yearly
 * @property bool $is_default
 */
class NumberSeries extends Model
{
    /** @use HasFactory<NumberSeriesFactory> */
    use HasFactory;

    protected $table = 'number_series';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'doc_type' => DocumentType::class,
            'next_number' => 'integer',
            'last_year' => 'integer',
            'reset_yearly' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Výchozí řada je pro každý typ dokladu nejvýš jedna.
        static::saved(function (NumberSeries $series): void {
            if ($series->is_default) {
                static::query()
                    ->where('doc_type', $series->doc_type->value)
                    ->where('id', '!=', $series->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /** Výchozí řada typu dokladu, jinak nejstarší řada téhož typu. */
    public static function defaultFor(DocumentType $type): ?self
    {
        return static::query()
            ->where('doc_type', $type->value)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
