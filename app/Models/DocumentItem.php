<?php

namespace App\Models;

use App\Domain\Documents\Exceptions\DocumentLockedException;
use Database\Factories\DocumentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $qty
 * @property string $unit_price
 * @property string $discount_percent
 * @property string $vat_rate
 * @property string $line_total
 */
class DocumentItem extends Model
{
    /** @use HasFactory<DocumentItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Položky zamčeného (vystaveného) dokladu nejde přidat, změnit ani smazat.
        static::saving(fn (DocumentItem $item) => $item->assertDocumentUnlocked());
        static::deleting(fn (DocumentItem $item) => $item->assertDocumentUnlocked());
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<PriceItem, $this> */
    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }

    private function assertDocumentUnlocked(): void
    {
        $ids = array_filter([
            $this->document_id,
            $this->getOriginal('document_id'),
        ]);

        if ($ids !== [] && Document::query()->whereIn('id', $ids)->whereNotNull('locked_at')->exists()) {
            throw DocumentLockedException::forItems();
        }
    }
}
