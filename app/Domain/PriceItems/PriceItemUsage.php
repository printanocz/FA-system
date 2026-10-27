<?php

namespace App\Domain\PriceItems;

use App\Domain\Contacts\ContactStatistics;
use App\Domain\Documents\DocumentType;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * „Na fakturách“: součet množství položky na fakturách vystavených v roce. Počítají se stejné
 * faktury jako do obratu kontaktu (vystavená, odeslaná, částečně uhrazená, uhrazená).
 */
final class PriceItemUsage
{
    /**
     * Přidá sloupec `used_qty` (součet množství v roce) a `is_used` (položka je na jakémkoli dokladu),
     * seřadí podle použití a pak abecedně.
     *
     * @param  Builder<PriceItem>  $query
     * @return Builder<PriceItem>
     */
    public static function apply(Builder $query, int $year): Builder
    {
        [$from, $to] = ContactStatistics::yearRange($year);

        return $query
            ->withSum(['documentItems as used_qty' => fn (Builder $items) => $items
                ->whereHas('document', fn (Builder $document) => $document
                    ->where('type', DocumentType::Invoice)
                    ->whereIn('status', [...ContactStatistics::ISSUED_STATUSES])
                    ->whereBetween('issue_date', [$from, $to])),
            ], 'qty')
            ->withExists('documentItems as is_used')
            ->orderByDesc('used_qty')
            ->orderBy('name');
    }
}
