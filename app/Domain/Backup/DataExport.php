<?php

namespace App\Domain\Backup;

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
 * Nešifrovaný export dat ke stažení: PDF všech vystavených faktur a CSV (faktury, položky,
 * úhrady, kontakty, ceník). Vytváří se za běhu v dočasném souboru, nikam se neukládá.
 */
class DataExport
{
    public function __construct(private readonly InvoicePdf $pdf) {}

    /** Vytvoří ZIP v dočasném souboru a vrátí jeho cestu; volající ho po odeslání smaže. */
    public function build(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new BackupException('Na serveru chybí PHP rozšíření zip.');
        }

        $path = tempnam(sys_get_temp_dir(), 'splatno-export-');
        if ($path === false) {
            throw new BackupException('Dočasný soubor exportu nejde vytvořit.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new BackupException('ZIP exportu nejde vytvořit.');
        }

        try {
            $invoices = Document::query()->where('type', 'invoice')->with(['contact', 'items', 'payments'])->orderBy('id')->get();

            foreach ($invoices as $invoice) {
                if ($invoice->isLocked() && $invoice->number !== null) {
                    $zip->addFromString('faktury-pdf/Faktura-'.preg_replace('/[^A-Za-z0-9._-]+/', '_', $invoice->number).'.pdf', $this->pdf->contents($invoice));
                }
            }

            $zip->addFromString('faktury.csv', $this->csv(
                ['cislo', 'stav', 'odberatel', 'ico', 'datum_vystaveni', 'datum_splatnosti', 'vs', 'mena', 'zaklad', 'dph', 'zaokrouhleni', 'celkem', 'uhrazeno', 'odeslano', 'uhrazeno_dne'],
                $invoices->map(fn (Document $d): array => [
                    $this->number($d), $d->status->value,
                    $d->buyer_snapshot['name'] ?? $d->contact?->name,
                    $d->buyer_snapshot['ico'] ?? $d->contact?->ico,
                    $d->issue_date?->toDateString(), $d->due_date?->toDateString(), $d->vs, $d->currency,
                    $d->subtotal, $d->vat_total, $d->rounding, $d->total, $d->paid_total,
                    $d->sent_at?->toDateTimeString(), $d->paid_at?->toDateString(),
                ])->all(),
            ));

            $zip->addFromString('polozky.csv', $this->csv(
                ['faktura', 'poradi', 'nazev', 'popis', 'mnozstvi', 'jednotka', 'cena_za_jednotku', 'celkem'],
                $invoices->flatMap(fn (Document $d) => $d->items->map(fn (DocumentItem $i): array => [
                    $this->number($d), $i->position, $i->name, $i->description, $i->qty, $i->unit, $i->unit_price, $i->line_total,
                ]))->all(),
            ));

            $zip->addFromString('uhrady.csv', $this->csv(
                ['faktura', 'castka', 'mena', 'datum', 'zpusob', 'poznamka'],
                $invoices->flatMap(fn (Document $d) => $d->payments->map(fn (Payment $p): array => [
                    $this->number($d), $p->amount, $p->currency, $p->paid_at->toDateString(), $p->method->value, $p->note,
                ]))->all(),
            ));

            $zip->addFromString('kontakty.csv', $this->csv(
                ['nazev', 'ico', 'dic', 'ulice', 'mesto', 'psc', 'zeme', 'email', 'email_kopie', 'telefon', 'splatnost_dnu', 'sleva_procent', 'poznamka', 'archivovan'],
                Contact::query()->orderBy('id')->get()->map(fn (Contact $c): array => [
                    $c->name, $c->ico, $c->dic, $c->street, $c->city, $c->zip, $c->country, $c->email,
                    implode(', ', $c->email_cc ?? []), $c->phone, $c->default_due_days, $c->default_discount_percent,
                    $c->note, $c->archived_at?->toDateString(),
                ])->all(),
            ));

            $zip->addFromString('cenik.csv', $this->csv(
                ['nazev', 'kod', 'jednotka', 'cena', 'mena', 'sazba_dph', 'poznamka', 'archivovano'],
                PriceItem::query()->orderBy('id')->get()->map(fn (PriceItem $p): array => [
                    $p->name, $p->code, $p->unit, $p->unit_price, $p->currency, $p->vat_rate, $p->note, $p->archived_at?->toDateString(),
                ])->all(),
            ));

            $zip->addFromString('INFO.txt', 'Export dat Splatno, '.Carbon::now()->format('j. n. Y H:i')."\nCSV: UTF-8, oddělovač středník, desetinná tečka.\n");

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

    /**
     * CSV pro české Excely: UTF-8 s BOM, oddělovač středník.
     *
     * @param  list<string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new BackupException('CSV nejde vytvořit.');
        }

        fputcsv($handle, $header, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (mixed $v): string => $v === null ? '' : (string) $v, $row), ';', '"', '');
        }
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$content;
    }
}
