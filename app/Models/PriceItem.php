<?php

namespace App\Models;

use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Položka ceníku. `note` je popis, který se nabízí na faktuře.
 *
 * @property string $name
 * @property string|null $note
 * @property string|null $code
 * @property string|null $unit
 * @property string $unit_price
 * @property string $currency
 * @property string $vat_rate
 * @property Carbon|null $archived_at
 */
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
            'archived_at' => 'datetime',
        ];
    }

    /** @return HasMany<DocumentItem, $this> */
    public function documentItems(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Hledání podle názvu a kódu.
     *
     * @param  Builder<PriceItem>  $query
     * @return Builder<PriceItem>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        return $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhere('code', 'like', $like));
    }
}
