<?php

namespace App\Http\Controllers\PriceItems;

use App\Actions\PriceItems\DeletePriceItem;
use App\Actions\PriceItems\SavePriceItem;
use App\Domain\PriceItems\PriceItemUsage;
use App\Http\Controllers\Controller;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ceník: tabulka položek, úpravy v panelu (mobil: Sheet) a JSON pro našeptávač v editoru faktury. */
class PriceItemController extends Controller
{
    /** Nejvyšší počet položek v našeptávači. */
    private const SUGGEST_LIMIT = 8;

    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $archived = $request->boolean('archivovane');
        $year = now()->year;

        $items = PriceItemUsage::apply(
            PriceItem::query()
                ->search($query)
                ->when(! $archived, fn (Builder $q) => $q->whereNull('archived_at')),
            $year,
        )->get();

        return Inertia::render('PriceItems/Index', [
            'items' => $items->map(fn (PriceItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'note' => $item->note ?? '',
                'code' => $item->code ?? '',
                'unit' => $item->unit ?? '',
                'currency' => $item->currency,
                'unit_price' => $item->unit_price,
                'used_qty' => $item->getAttribute('used_qty') === null ? null : rtrim(rtrim(number_format((float) $item->getAttribute('used_qty'), 4, '.', ''), '0'), '.'),
                'is_used' => (bool) $item->getAttribute('is_used'),
                'archived' => $item->isArchived(),
            ])->values(),
            'filters' => ['q' => $query, 'archived' => $archived],
            'year' => $year,
        ]);
    }

    public function store(Request $request, SavePriceItem $save): RedirectResponse
    {
        $save($request->all());

        return back();
    }

    public function update(Request $request, PriceItem $priceItem, SavePriceItem $save): RedirectResponse
    {
        $save($request->all(), $priceItem);

        return back();
    }

    /** Smaže položku bez použití, jinak ji archivuje. */
    public function destroy(PriceItem $priceItem, DeletePriceItem $delete): RedirectResponse
    {
        $delete($priceItem);

        return back();
    }

    public function restore(PriceItem $priceItem): RedirectResponse
    {
        $priceItem->forceFill(['archived_at' => null])->save();

        return back();
    }

    /** Našeptávač pro editor faktury: nearchivované položky podle použití. */
    public function search(Request $request): JsonResponse
    {
        $items = PriceItemUsage::apply(
            PriceItem::query()
                ->whereNull('archived_at')
                ->search((string) $request->query('q', '')),
            now()->year,
        )->limit(self::SUGGEST_LIMIT)->get();

        return response()->json([
            'items' => $items->map(fn (PriceItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->note,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'currency' => $item->currency,
            ])->values(),
        ]);
    }
}
