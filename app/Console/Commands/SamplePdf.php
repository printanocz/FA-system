<?php

namespace App\Console\Commands;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\PaymentMethod;
use App\Domain\Pdf\InvoicePdf;
use App\Models\Document;
use App\Models\DocumentItem;
use Brick\Math\BigDecimal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Vygeneruje ukázkovou fakturu z pevných dat bez databáze. Slouží ke kontrole vzhledu PDF
 * (spouští se v CI a výsledek se nahrává jako artifact).
 */
class SamplePdf extends Command
{
    protected $signature = 'app:sample-pdf {path : Kam uložit PDF} {--items=3 : Počet položek} {--cash : Platba hotově}';

    protected $description = 'Vygeneruje ukázkovou fakturu jako PDF (kontrola vzhledu bez databáze)';

    public function handle(InvoicePdf $pdf): int
    {
        $document = $this->sample(
            items: max(1, (int) $this->option('items')),
            cash: (bool) $this->option('cash'),
        );

        $path = (string) $this->argument('path');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $pdf->render($document));

        $this->info('PDF uloženo: '.$path);

        return self::SUCCESS;
    }

    private function sample(int $items, bool $cash): Document
    {
        $rows = [
            ['Návrh obalu Kváskový chléb, 3 varianty', 'Vektorové podklady, 2 kola korektur', '1', 'ks', '14500.00'],
            ['Úpravy vybrané varianty, 2 kola korektur', null, '10', 'hod.', '600.00'],
            ['Příprava tiskových dat pro výrobce obalů', 'PDF/X-4, ořezové značky, spadávka 3 mm', '1', 'ks', '4000.00'],
        ];

        $collection = collect();
        $total = BigDecimal::zero();
        for ($i = 0; $i < $items; $i++) {
            [$name, $description, $qty, $unit, $price] = $rows[$i % count($rows)];
            $line = BigDecimal::of($qty)->multipliedBy($price)->toScale(2);
            $total = $total->plus($line);
            $collection->push((new DocumentItem)->forceFill([
                'position' => $i + 1,
                'name' => $name.($i >= count($rows) ? ' ('.($i + 1).')' : ''),
                'description' => $description,
                'qty' => $qty,
                'unit' => $unit,
                'unit_price' => $price,
                'line_total' => (string) $line,
            ]));
        }

        // forceFill: bez zápisu i bez dotazu na schéma tabulky, ukázka nepotřebuje databázi.
        $document = (new Document)->forceFill([
            'type' => DocumentType::Invoice,
            'status' => DocumentStatus::Sent,
            'number' => '2026062',
            'vs' => '2026062',
            'ks' => '0308',
            'currency' => 'CZK',
            'payment_method' => $cash ? PaymentMethod::Cash : PaymentMethod::BankTransfer,
            'issue_date' => '2026-10-12',
            'taxable_date' => '2026-10-12',
            'show_taxable_date' => false,
            'due_date' => '2026-10-26',
            'discount_percent' => '0.00',
            'rounding' => '0.00',
            'subtotal' => (string) $total,
            'vat_total' => '0.00',
            'total' => (string) $total,
            'paid_total' => '0.00',
            'text_before' => 'Fakturuji Vám za dodané služby:',
            'text_after' => 'Dovoluji si Vás upozornit, že v případě nedodržení data splatnosti uvedeného na faktuře Vám mohu účtovat zákonný úrok z prodlení.',
            'locked_at' => now(),
            'supplier_snapshot' => [
                'name' => 'Adam Fuják',
                'ico' => '07539266',
                'street' => 'Krásné Loučky 27/27',
                'zip' => '794 01',
                'city' => 'Krnov',
                'registration_note' => 'Fyzická osoba zapsaná v živnostenském rejstříku.',
                'email' => 'jsem@adamfujak.cz',
                'phone' => '775 549 910',
                'web' => 'www.adamfujak.cz',
                'vat_payer' => false,
            ],
            'buyer_snapshot' => [
                'name' => 'Pekárna Hrubý s.r.o.',
                'ico' => '08765432',
                'dic' => 'CZ08765432',
                'street' => 'Hlavní náměstí 14',
                'zip' => '792 01',
                'city' => 'Bruntál',
            ],
            'bank_account_snapshot' => [
                'name' => 'Česká spořitelna',
                'account_number' => '2401234567',
                'bank_code' => '0800',
                'iban' => 'CZ6508000000192000145399',
                'swift' => 'GIBACZPX',
                'currency' => 'CZK',
            ],
        ]);
        $document->setRelation('items', $collection);

        return $document;
    }
}
