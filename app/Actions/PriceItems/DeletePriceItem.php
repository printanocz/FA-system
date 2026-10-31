<?php

namespace App\Actions\PriceItems;

use App\Models\PriceItem;

/** Smaže položku, kterou nepoužívá žádný doklad. Použitou jen archivuje. */
final class DeletePriceItem
{
    /** @return bool true = smazáno, false = archivováno */
    public function __invoke(PriceItem $item): bool
    {
        if ($item->documentItems()->exists()) {
            $item->forceFill(['archived_at' => now()])->save();

            return false;
        }

        $item->delete();

        return true;
    }
}
