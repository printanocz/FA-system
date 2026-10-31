<?php

namespace App\Domain\Backup;

use App\Domain\Documents\DocumentType;
use App\Domain\Pdf\InvoicePdf;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Payment;
use App\Models\PriceItem;
use Illuminate\Support\Carbon;
use Throwable;
use ZipArchive;

/**
 * Nešifrovaný export dat ke stažení: PDF vystavených dokladů (složky podle typu) a CSV (doklady, položky,
 * úhrady, kontakty, ceník). Vytváří se za běhu v dočasném souboru, nikam se neukládá.
 */
class DataExport
{
    public function __construct(private readonly InvoicePdf $pdf) {}

    /** Vytvoří ZIP v dočasném souboru (storage/app/backup-work) a vrátí jeho cestu; volající ho po odeslání smaže. */
    public function build(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new BackupException('Na serveru chybí PHP rozšíření zip.');
        }

        $path = WorkDirectory::tempFile('export');

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($path);
            throw new BackupException('ZIP exportu nejde vytvořit.');
        }

        try {
            $documents = Document::query()->with(['contact', 'items', 'payments'])->orderBy('id')->get();

            foreach ($documents as $document) {
                if ($document->isLocked() && $document->number !== null) {
                    $zip->addFromString(
                        self::pdfFolder($document->type).'/'.self::pdfName($document),
                        $this->pdf->contents($document),
                    );
                }
            }

            $zip->addFromString('doklady.csv', $this->csv(
                ['typ', 'cislo', 'stav', 'odberatel', 'ico', 'datum_vystaveni', 'datum_splatnosti', 'vs', 'mena', 'zaklad', 'dph', 'zaokrouhleni', 'celkem', 'uhrazeno', 'odeslano', 'uhrazeno_dne'],
                $documents->map(fn (Document $d): array => [
                    self::typeLabel($d->type), $this->number($d), $d->status->value,
                    $d->buyer_snapshot['name'] ?? $d->contact?->name,
                    $d->buyer_snapshot['ico'] ?? $d->contact?->ico,
                    $d->issue_date?->toDateString(), $d->due_date?->toDateString(), $d->vs, $d->currency,
                    $d->subtotal, $d->vat_total, $d->rounding, $d->total, $d->paid_total,
                    $d->sent_at?->toDateTimeString(), $d->paid_at?->toDateString(),
                ])->all(),
                textColumns: [0, 1, 2, 3, 4, 7, 8],
            ));

            $zip->addFromString('polozky.csv', $this->csv(
                ['typ', 'cislo', 'poradi', 'nazev', 'popis', 'mnozstvi', 'jednotka', 'cena_za_jednotku', 'celkem'],
                $documents->flatMap(fn (Document $d) => $d->items->map(fn (DocumentItem $i): array => [
                    self::typeLabel($d->type), $this->number($d), $i->position, $i->name, $i->description, $i->qty, $i->unit, $i->unit_price, $i->line_total,
                ]))->all(),
                textColumns: [0, 1, 3, 4, 6],
            ));

            $zip->addFromString('uhrady.csv', $this->csv(
                ['typ', 'cislo', 'castka', 'mena', 'datum', 'zpusob', 'poznamka'],
                $documents->flatMap(fn (Document $d) => $d->payments->map(fn (Payment $p): array => [
                    self::typeLabel($d->type), $this->number($d), $p->amount, $p->currency, $p->paid_at->toDateString(), $p->method->value, $p->note,
                ]))->all(),
                textColumns: [0, 1, 3, 5, 6],
            ));

            $zip->addFromString('kontakty.csv', $this->csv(
                ['nazev', 'ico', 'dic', 'ulice', 'mesto', 'psc', 'zeme', 'email', 'email_kopie', 'telefon', 'splatnost_dnu', 'sleva_procent', 'poznamka', 'archivovan'],
                Contact::query()->orderBy('id')->get()->map(fn (Contact $c): array => [
                    $c->name, $c->ico, $c->dic, $c->street, $c->city, $c->zip, $c->country, $c->email,
                    implode(', ', $c->email_cc ?? []), $c->phone, $c->default_due_days, $c->default_discount_percent,
                    $c->note, $c->archived_at?->toDateString(),
                ])->all(),
                textColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 12],
            ));

            $zip->addFromString('cenik.csv', $this->csv(
                ['nazev', 'kod', 'jednotka', 'cena', 'mena', 'sazba_dph', 'poznamka', 'archivovano'],
                PriceItem::query()->orderBy('id')->get()->map(fn (PriceItem $p): array => [
                    $p->name, $p->code, $p->unit, $p->unit_price, $p->currency, $p->vat_rate, $p->note, $p->archived_at?->toDateString(),
                ])->all(),
                textColumns: [0, 1, 2, 4, 6],
            ));

            $zip->addFromString('INFO.txt', 'Export dat Splatno, '.Carbon::now()->format('j. n. Y H:i')."\nCSV: UTF-8, oddělovač středník, desetinná tečka.\nDoklady (doklady.csv, polozky.csv, uhrady.csv) jsou propojené sloupci typ + cislo.\nTextové hodnoty začínající znaky = + - @ nebo tabulátorem mají na začátku apostrof (ochrana před vzorci v Excelu).\n");

            if (! $zip->close()) {
                throw new BackupException('ZIP exportu se nepodařilo dokončit.');
            }
        } catch (BackupException $e) {
            @unlink($path);
            throw $e;
        } catch (Throwable $e) {
            @unlink($path);
            throw new BackupException('Export dat selhal: '.$e->getMessage(), previous: $e);
        }

        return $path;
    }

    public function filename(): string
    {
        return 'splatno-export-'.Carbon::now()->format('Y-m-d').'.zip';
    }

    private function number(Document $document): string
    {
        return $document->number ?? 'koncept-'.$document->id;
    }

    /** Česky pojmenovaný typ dokladu pro sloupec `typ`. */
    public static function typeLabel(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::Invoice => 'faktura',
            DocumentType::Quote => 'nabídka',
            DocumentType::Proforma => 'zálohová faktura',
            DocumentType::CreditNote => 'dobropis',
        };
    }

    /** Složka s PDF podle typu dokladu. */
    public static function pdfFolder(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::Invoice => 'faktury-pdf',
            DocumentType::Quote => 'nabidky-pdf',
            DocumentType::Proforma => 'zalohove-faktury-pdf',
            DocumentType::CreditNote => 'dobropisy-pdf',
        };
    }

    private static function pdfName(Document $document): string
    {
        $prefix = match ($document->type) {
            DocumentType::Invoice => 'Faktura',
            DocumentType::Quote => 'Nabidka',
            DocumentType::Proforma => 'Zalohova-faktura',
            DocumentType::CreditNote => 'Dobropis',
        };

        return $prefix.'-'.preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $document->number).'.pdf';
    }

    /**
     * Ochrana proti vzorcům v Excelu: textová hodnota začínající = + - @ nebo tabulátorem
     * (případně CR) dostane na začátek apostrof. Čísla a data se nemění.
     */
    public static function guardFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * CSV pro české Excely: UTF-8 s BOM, oddělovač středník. Sloupce v $textColumns jsou textové
     * a chrání se proti vzorcům; ostatní (čísla, data) se zapisují beze změny.
     *
     * @param  list<string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     * @param  list<int>  $textColumns
     */
    private function csv(array $header, array $rows, array $textColumns = []): string
    {
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new BackupException('CSV nejde vytvořit.');
        }

        fputcsv($handle, $header, ';', '"', '');
        foreach ($rows as $row) {
            $cells = [];
            foreach (array_values($row) as $i => $value) {
                $text = $value === null ? '' : (string) $value;
                $cells[] = in_array($i, $textColumns, true) ? self::guardFormula($text) : $text;
            }
            fputcsv($handle, $cells, ';', '"', '');
        }
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$content;
    }
}
