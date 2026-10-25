<?php

namespace App\Domain\Settings;

use App\Models\Contact;
use App\Models\Setting;

/**
 * Výchozí hodnoty dokladů z Nastavení (tabulka `settings`, klíče s předponou `documents.`).
 * Nenastavená hodnota se vrací jako výchozí z DEFAULTS.
 */
class DocumentSettings
{
    /** @var array<string, int|bool|string|null> */
    public const DEFAULTS = [
        'due_days' => 14,
        'quote_validity_days' => 14,
        'constant_symbol' => null,
        'round_total' => false,
        'text_before' => null,
        'text_after' => null,
    ];

    private const PREFIX = 'documents.';

    /** Výchozí splatnost faktury ve dnech. */
    public function dueDays(): int
    {
        return (int) $this->get('due_days');
    }

    /** Splatnost kontaktu má přednost před výchozí z Nastavení. */
    public function dueDaysFor(?Contact $contact): int
    {
        return $contact?->default_due_days ?? $this->dueDays();
    }

    /** Výchozí platnost nabídky ve dnech. */
    public function quoteValidityDays(): int
    {
        return (int) $this->get('quote_validity_days');
    }

    public function constantSymbol(): ?string
    {
        $value = $this->get('constant_symbol');

        return $value === null ? null : (string) $value;
    }

    public function roundTotal(): bool
    {
        return (bool) $this->get('round_total');
    }

    public function textBefore(): ?string
    {
        $value = $this->get('text_before');

        return $value === null ? null : (string) $value;
    }

    public function textAfter(): ?string
    {
        $value = $this->get('text_after');

        return $value === null ? null : (string) $value;
    }

    /** @return array<string, int|bool|string|null> */
    public function all(): array
    {
        $stored = Setting::query()
            ->where('key', 'like', self::PREFIX.'%')
            ->pluck('value', 'key');

        $values = [];
        foreach (self::DEFAULTS as $name => $default) {
            $values[$name] = $stored->has(self::PREFIX.$name) ? $stored[self::PREFIX.$name] : $default;
        }

        return $values;
    }

    /**
     * Uloží už zvalidované hodnoty (jen známé klíče). Validaci dělá UpdateDocumentSettings.
     *
     * @param  array<string, int|bool|string|null>  $values
     */
    public function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $name => $value) {
            Setting::query()->updateOrCreate(['key' => self::PREFIX.$name], ['value' => $value]);
        }
    }

    /**
     * Zapíše výchozí hodnotu jen tam, kde nastavení ještě neexistuje (pro seeder).
     *
     * @param  array<string, int|bool|string|null>  $values
     */
    public function putMissing(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $name => $value) {
            Setting::query()->firstOrCreate(['key' => self::PREFIX.$name], ['value' => $value]);
        }
    }

    private function get(string $name): mixed
    {
        $row = Setting::query()->where('key', self::PREFIX.$name)->first();

        return $row === null ? self::DEFAULTS[$name] : $row->value;
    }
}
