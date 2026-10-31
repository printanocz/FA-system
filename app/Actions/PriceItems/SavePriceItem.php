<?php

namespace App\Actions\PriceItems;

use App\Models\PriceItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Uloží novou nebo upravenou položku ceníku (normalizace, validace, kód je unikátní). */
final class SavePriceItem
{
    public const CURRENCIES = ['CZK', 'EUR'];

    /** @param array<string, mixed> $input */
    public function __invoke(array $input, ?PriceItem $item = null): PriceItem
    {
        $item ??= new PriceItem;

        $input = $this->normalize($input);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('price_items', 'code')->ignore($item->id)],
            'unit' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', Rule::in(self::CURRENCIES)],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [
            'name.required' => 'Zadejte název položky.',
            'name.max' => 'Název může mít nejvýše 255 znaků.',
            'note.max' => 'Popis může mít nejvýše 2000 znaků.',
            'code.unique' => 'Tento kód už používá jiná položka.',
            'code.max' => 'Kód může mít nejvýše 50 znaků.',
            'unit.max' => 'Měrná jednotka může mít nejvýše 20 znaků.',
            'currency.required' => 'Vyberte měnu.',
            'currency.in' => 'Vyberte měnu CZK nebo EUR.',
            'unit_price.required' => 'Zadejte cenu za měrnou jednotku.',
            'unit_price.numeric' => 'Cena musí být číslo, například 400,00.',
            'unit_price.min' => 'Cena nemůže být záporná.',
            'unit_price.max' => 'Cena je příliš vysoká.',
        ])->validate();

        $data['unit_price'] = BigDecimal::of($data['unit_price'])->toScale(2, RoundingMode::HALF_UP)->__toString();

        $item->fill($data)->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        $text = static function (mixed $value): ?string {
            $value = is_string($value) ? trim($value) : null;

            return $value === '' ? null : $value;
        };

        $price = $text($input['unit_price'] ?? null);
        if ($price !== null) {
            $price = str_replace([' ', "\u{00A0}"], '', $price);
            $price = str_replace(',', '.', $price);
        }

        return [
            'name' => $text($input['name'] ?? null),
            'note' => $text($input['note'] ?? null),
            'code' => $text($input['code'] ?? null),
            'unit' => $text($input['unit'] ?? null),
            'currency' => strtoupper($text($input['currency'] ?? null) ?? 'CZK'),
            'unit_price' => $price,
        ];
    }
}
