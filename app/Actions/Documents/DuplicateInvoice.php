<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentType;
use App\Models\CompanyProfile;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Kopie faktury jako nový koncept: stejný odběratel, položky a texty, ale dnešní datum
 * a výchozí splatnost z Nastavení (nebo z kontaktu). Číslo, úhrady a interní poznámka se nekopírují.
 */
final class DuplicateInvoice
{
    public function __construct(
        private readonly CreateDraftDocument $create,
        private readonly RecalculateDocument $recalculate,
    ) {}

    public function __invoke(Document $source): Document
    {
        return DB::transaction(function () use ($source): Document {
            $copy = ($this->create)(DocumentType::Invoice, $source->contact, [
                'payment_method' => $source->payment_method,
                'bank_account_id' => $source->bank_account_id,
                'discount_percent' => $source->discount_percent,
                'round_total' => $source->round_total,
                'show_taxable_date' => $source->show_taxable_date,
                'text_before' => $source->text_before,
                'text_after' => $source->text_after,
            ]);

            foreach ($source->items()->get() as $item) {
                $copy->items()->create([
                    'position' => $item->position,
                    'name' => $item->name,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate' => $item->vat_rate,
                    'price_item_id' => $item->price_item_id,
                ]);
            }

            ($this->recalculate)($copy, (bool) CompanyProfile::current()?->vat_payer);

            return $copy->fresh() ?? $copy;
        });
    }
}
