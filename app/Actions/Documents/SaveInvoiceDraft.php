<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentLockedException;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Uloží koncept faktury z editoru: hlavička, položky (nahradí se celé), přepočet částek.
 * Zamčený (vystavený) doklad se nemění. Nový koncept vzniká přes CreateDraftDocument.
 */
final class SaveInvoiceDraft
{
    public const PAYMENT_METHODS = ['bank_transfer', 'cash'];

    public function __construct(
        private readonly CreateDraftDocument $create,
        private readonly RecalculateDocument $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(array $input, ?Document $document = null): Document
    {
        if ($document !== null && $document->isLocked()) {
            throw DocumentLockedException::forItems();
        }

        $data = $this->validate($this->normalize($input), $document);
        $items = $data['items'] ?? [];
        unset($data['items']);

        return DB::transaction(function () use ($data, $items, $document): Document {
            $contact = isset($data['contact_id']) ? Contact::query()->find($data['contact_id']) : null;

            if ($document === null) {
                $document = ($this->create)(DocumentType::Invoice, $contact, $data);
            } else {
                $document->fill($data)->save();
            }

            $document->items()->delete();
            foreach (array_values($items) as $position => $item) {
                $document->items()->create([
                    'position' => $position + 1,
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'qty' => $item['qty'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'vat_rate' => '0.00',
                    'price_item_id' => $item['price_item_id'] ?? null,
                ]);
            }

            ($this->recalculate)($document, (bool) CompanyProfile::current()?->vat_payer);

            return $document->fresh() ?? $document;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, ?Document $document): array
    {
        return Validator::make($input, [
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id'), function (string $attribute, mixed $value, \Closure $fail) use ($document): void {
                $archived = Contact::query()->whereKey($value)->whereNotNull('archived_at')->exists();
                if ($archived && $document?->contact_id !== (int) $value) {
                    $fail('Archivovaný kontakt nelze vybrat. Nejdřív ho v Adresáři obnovte.');
                }
            }],
            'issue_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'taxable_date' => ['nullable', 'date_format:Y-m-d'],
            'order_number' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')],
            'text_before' => ['nullable', 'string', 'max:5000'],
            'text_after' => ['nullable', 'string', 'max:5000'],
            'note_internal' => ['nullable', 'string', 'max:5000'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'round_total' => ['required', 'boolean'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.qty' => ['required', 'numeric', 'min:0.0001', 'max:9999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:-9999999999999', 'max:9999999999999'],
            'items.*.price_item_id' => ['nullable', 'integer', Rule::exists('price_items', 'id')],
        ], self::messages(), self::attributes())->validate();
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'issue_date.required' => 'Zadejte datum vystavení.',
            'issue_date.date_format' => 'Zadejte datum ve tvaru 25. 10. 2026.',
            'due_date.required' => 'Zadejte splatnost.',
            'due_date.date_format' => 'Zadejte datum ve tvaru 8. 11. 2026.',
            'due_date.after_or_equal' => 'Splatnost nemůže být před datem vystavení.',
            'taxable_date.date_format' => 'Zadejte datum ve tvaru 25. 10. 2026.',
            'order_number.max' => 'Číslo objednávky může mít nejvýše 50 znaků.',
            'payment_method.required' => 'Vyberte způsob úhrady.',
            'payment_method.in' => 'Vyberte způsob úhrady.',
            'bank_account_id.exists' => 'Vybraný bankovní účet neexistuje.',
            'contact_id.exists' => 'Vybraný odběratel neexistuje.',
            'discount_percent.required' => 'Zadejte slevu v procentech (0 až 100).',
            'discount_percent.numeric' => 'Sleva musí být číslo, například 5.',
            'discount_percent.min' => 'Sleva musí být mezi 0 a 100 %.',
            'discount_percent.max' => 'Sleva musí být mezi 0 a 100 %.',
            'items.max' => 'Faktura může mít nejvýše 100 položek.',
            'items.*.name.required' => 'Zadejte popis položky.',
            'items.*.name.max' => 'Popis položky může mít nejvýše 255 znaků.',
            'items.*.qty.required' => 'Zadejte počet.',
            'items.*.qty.numeric' => 'Počet musí být číslo.',
            'items.*.qty.min' => 'Počet musí být větší než nula.',
            'items.*.qty.max' => 'Počet je příliš velký.',
            'items.*.unit.max' => 'Měrná jednotka může mít nejvýše 20 znaků.',
            'items.*.unit_price.required' => 'Zadejte cenu za jednotku.',
            'items.*.unit_price.numeric' => 'Cena musí být číslo, například 400,00.',
            'items.*.unit_price.min' => 'Cena je příliš nízká.',
            'items.*.unit_price.max' => 'Cena je příliš vysoká.',
            'text_before.max' => 'Text může mít nejvýše 5000 znaků.',
            'text_after.max' => 'Text může mít nejvýše 5000 znaků.',
            'note_internal.max' => 'Poznámka může mít nejvýše 5000 znaků.',
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [];
    }

    /**
     * Čísla s desetinnou čárkou a mezerami v tisících, prázdné texty jako null, prázdné řádky pryč.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $text = static function (mixed $value): ?string {
            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        $number = static function (mixed $value) use ($text): ?string {
            $value = $text($value);

            return $value === null ? null : str_replace(',', '.', str_replace([' ', "\u{00A0}"], '', $value));
        };

        $items = [];
        foreach ((array) ($input['items'] ?? []) as $row) {
            $row = (array) $row;
            $item = [
                'name' => $text($row['name'] ?? null),
                'description' => $text($row['description'] ?? null),
                'qty' => $number($row['qty'] ?? null),
                'unit' => $text($row['unit'] ?? null),
                'unit_price' => $number($row['unit_price'] ?? null),
                'price_item_id' => $text($row['price_item_id'] ?? null),
            ];
            // Řádek bez popisu a ceny (jen výchozí počet a jednotka) je prázdný.
            if ($item['name'] === null && $item['description'] === null && $item['unit_price'] === null) {
                continue;
            }
            $items[] = $item;
        }

        return [
            'contact_id' => $text($input['contact_id'] ?? null),
            'issue_date' => $text($input['issue_date'] ?? null),
            'due_date' => $text($input['due_date'] ?? null),
            'taxable_date' => $text($input['taxable_date'] ?? null),
            'order_number' => $text($input['order_number'] ?? null),
            'payment_method' => $text($input['payment_method'] ?? null) ?? 'bank_transfer',
            'bank_account_id' => $text($input['bank_account_id'] ?? null),
            'text_before' => $text($input['text_before'] ?? null),
            'text_after' => $text($input['text_after'] ?? null),
            'note_internal' => $text($input['note_internal'] ?? null),
            'discount_percent' => $number($input['discount_percent'] ?? null) ?? '0',
            'round_total' => filter_var($input['round_total'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'items' => $items,
        ];
    }
}
