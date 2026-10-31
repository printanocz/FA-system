<?php

namespace App\Domain\Mail;

use App\Models\Document;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

/** Dosazení proměnných ({cislo}, {castka}, …) do předmětu a textu e-mailu. */
final class EmailTemplate
{
    /** Proměnné, které šablony umějí (popis pro nápovědu v Nastavení). */
    public const VARIABLES = [
        'cislo' => 'číslo faktury',
        'castka' => 'částka k úhradě',
        'splatnost' => 'datum splatnosti',
        'vs' => 'variabilní symbol',
        'ucet' => 'číslo účtu',
        'odberatel' => 'jméno odběratele',
        'dodavatel' => 'jméno dodavatele (podpis)',
        'dni_po_splatnosti' => 'počet dní po splatnosti',
    ];

    /** @param  array<string, string>  $variables */
    public static function render(string $template, array $variables): string
    {
        $pairs = [];
        foreach ($variables as $name => $value) {
            $pairs['{'.$name.'}'] = $value;
        }

        return strtr($template, $pairs);
    }

    /**
     * Hodnoty proměnných pro konkrétní fakturu (ze snapshotů, takže odpovídají PDF).
     *
     * @return array<string, string>
     */
    public static function variables(Document $document, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $bank = $document->bank_account_snapshot;
        $account = is_array($bank) && ! empty($bank['account_number'])
            ? $bank['account_number'].'/'.($bank['bank_code'] ?? '')
            : '';
        $overdueDays = $document->due_date !== null && $document->due_date->copy()->startOfDay()->lt($today)
            ? (int) $document->due_date->copy()->startOfDay()->diffInDays($today)
            : 0;

        return [
            'cislo' => (string) $document->number,
            'castka' => self::money((string) $document->remainingAmount(), $document->currency),
            'splatnost' => $document->due_date?->format('j. n. Y') ?? '',
            'vs' => (string) $document->vs,
            'ucet' => $account,
            'odberatel' => (string) ($document->buyer_snapshot['name'] ?? ''),
            'dodavatel' => (string) ($document->supplier_snapshot['name'] ?? ''),
            'dni_po_splatnosti' => (string) $overdueDays,
        ];
    }

    /** Částka česky: 24 500,00 Kč (mezera jako oddělovač tisíců). */
    public static function money(string $amount, string $currency): string
    {
        $value = BigDecimal::of($amount)->toScale(2);
        $formatted = number_format((float) (string) $value, 2, ',', ' ');

        return $formatted.' '.($currency === 'CZK' ? 'Kč' : $currency);
    }
}
