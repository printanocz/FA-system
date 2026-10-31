<?php

namespace App\Domain\Mail;

use App\Models\Setting;

/**
 * Šablony e-mailů a odesílatel z Nastavení (tabulka `settings`, klíče s předponou `emails.`).
 * Nenastavená hodnota se vrací jako výchozí z DEFAULTS.
 */
class EmailSettings
{
    /** @var array<string, bool|string> */
    public const DEFAULTS = [
        'sender_name' => '',
        'copy_to_me' => false,
        'invoice_subject' => 'Faktura {cislo}',
        'invoice_body' => "Dobrý den,\n\nv příloze Vám zasílám fakturu {cislo} na částku {castka} se splatností {splatnost}.\n\nPlatbu prosím proveďte na účet {ucet}, variabilní symbol {vs}.\n\nDěkuji a přeji hezký den,\n{dodavatel}",
        'reminder_subject' => 'Upomínka: faktura {cislo} po splatnosti',
        'reminder_body' => "Dobrý den,\n\npři kontrole plateb jsem zjistil, že faktura {cislo} na částku {castka} je po splatnosti ({dni_po_splatnosti} dní, splatnost byla {splatnost}).\n\nPokud jste platbu už odeslali, považujte prosím tuto zprávu za bezpředmětnou. Jinak Vás prosím o úhradu na účet {ucet}, variabilní symbol {vs}. Fakturu posílám znovu v příloze.\n\nDěkuji,\n{dodavatel}",
    ];

    private const PREFIX = 'emails.';

    /** @return array<string, bool|string> */
    public function all(): array
    {
        $stored = Setting::query()
            ->where('key', 'like', self::PREFIX.'%')
            ->pluck('value', 'key');

        $values = [];
        foreach (self::DEFAULTS as $name => $default) {
            $key = self::PREFIX.$name;
            $values[$name] = $stored->has($key) && $stored[$key] !== null ? $stored[$key] : $default;
        }

        return $values;
    }

    /**
     * Uloží už zvalidované hodnoty (jen známé klíče).
     *
     * @param  array<string, bool|string|null>  $values
     */
    public function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $name => $value) {
            Setting::query()->updateOrCreate(['key' => self::PREFIX.$name], ['value' => $value]);
        }
    }

    public function copyToMe(): bool
    {
        return (bool) $this->all()['copy_to_me'];
    }

    /** Jméno odesílatele; bez vyplněného nastavení název firmy. */
    public function senderName(?string $companyName): string
    {
        $name = trim((string) $this->all()['sender_name']);

        return $name !== '' ? $name : (string) ($companyName ?: config('mail.from.name'));
    }
}
