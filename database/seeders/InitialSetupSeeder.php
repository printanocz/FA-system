<?php

namespace Database\Seeders;

use App\Domain\Documents\DocumentType;
use App\Domain\Settings\DocumentSettings;
use App\Models\NumberSeries;
use Illuminate\Database\Seeder;

/**
 * Výchozí nastavení nové instalace: číselné řady a výchozí hodnoty dokladů.
 * Bezpečné spustit opakovaně, existující řady a nastavení nepřepisuje.
 *
 * php artisan db:seed --class=InitialSetupSeeder
 */
class InitialSetupSeeder extends Seeder
{
    public function run(DocumentSettings $settings): void
    {
        // Faktury: 2026061 (bez prefixu). Nabídky: N2026001.
        $this->series(DocumentType::Invoice, '');
        $this->series(DocumentType::Quote, 'N');

        $settings->putMissing([
            'due_days' => 14,
            'quote_validity_days' => 14,
            'constant_symbol' => '0308',
            'round_total' => false,
            'text_before' => 'Fakturuji Vám za dodané služby:',
            'text_after' => 'V případě prodlení s úhradou účtuji zákonný úrok z prodlení.',
        ]);
    }

    private function series(DocumentType $type, string $prefix): void
    {
        NumberSeries::query()->firstOrCreate(
            ['doc_type' => $type->value, 'prefix' => $prefix],
            ['format' => '{YYYY}{NNN}', 'next_number' => 1, 'reset_yearly' => true, 'is_default' => true],
        );
    }
}
