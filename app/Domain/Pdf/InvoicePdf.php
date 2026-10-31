<?php

namespace App\Domain\Pdf;

use App\Domain\Documents\PaymentMethod;
use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use App\Domain\Payments\IbanConverter;
use App\Domain\Payments\QrPaymentService;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * PDF faktury (artboard „PDF faktury — A4“): HTML šablona `pdf.invoice` → mPDF.
 *
 * Vystavený doklad se vykresluje výhradně ze snapshotů (dodavatel, odběratel, účet), nikdy
 * z živého kontaktu. Koncept nemá snapshoty, vezme se z aktuálních dat a do PDF se přidá
 * vodoznak „KONCEPT“.
 * Archivní soubor vystaveného dokladu se vytvoří jednou a už se nepřepisuje.
 */
class InvoicePdf
{
    /** Značka v HTML, která odděluje obsah od platebního bloku přilepeného ke spodku poslední strany. */
    public const PAYMENT_MARKER = '<!--pdf:payment-->';

    public const WATERMARK = 'KONCEPT';

    /** Průhlednost vodoznaku; v PDF se objeví jako „/ca 0.08“. */
    public const WATERMARK_ALPHA = 0.08;

    /** Spodní okraj strany v mm; k němu se přilepí platební blok a „Celkem k úhradě“. */
    private const MARGIN_BOTTOM_MM = 22;

    /** Výška bloku s QR a platebními údaji v mm (32 mm QR + popisek); blok se zarovná ke spodnímu okraji. */
    private const PAYMENT_HEIGHT_MM = 37;

    private const PAYMENT_LABELS = [
        'bank_transfer' => 'Převodem',
        'cash' => 'Hotově',
        'card' => 'Kartou',
        'cod' => 'Dobírkou',
    ];

    public function __construct(
        private readonly QrPaymentService $qr,
        private readonly PdfFonts $fonts,
        private readonly IbanConverter $ibans = new IbanConverter,
    ) {}

    /** HTML dokladu (bez mPDF); stejný výstup, ze kterého se pak dělá PDF. */
    public function html(Document $document): string
    {
        return $this->view($document, $this->fonts->resolve()['families']);
    }

    /** Binární PDF. Koncept má vodoznak „KONCEPT“. */
    public function render(Document $document): string
    {
        $resolved = $this->fonts->resolve();
        $html = $this->view($document, $resolved['families']);
        [$body, $payment] = array_pad(explode(self::PAYMENT_MARKER, $html, 2), 2, '');

        $mpdf = $this->makeMpdf($resolved['config'], $document);

        // Vodoznak se nastavuje v PHP, značku <watermarktext> v HTML mPDF ignoruje.
        if (! $document->isLocked()) {
            $mpdf->SetWatermarkText(self::WATERMARK, self::WATERMARK_ALPHA);
            $mpdf->showWatermarkText = true;
        }

        $mpdf->WriteHTML($body);

        // Platební blok na spodek poslední strany; když se pod obsah nevejde, začne nová strana.
        $limit = $mpdf->h - $mpdf->bMargin;
        if ($mpdf->y + self::PAYMENT_HEIGHT_MM > $limit) {
            $mpdf->AddPage();
        }
        $mpdf->SetY($limit - self::PAYMENT_HEIGHT_MM);
        $mpdf->WriteHTML($payment);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Obsah PDF ke stažení: u vystaveného dokladu archivní soubor (případně ho dogeneruje),
     * u konceptu čerstvé PDF s vodoznakem, které se neukládá.
     */
    public function contents(Document $document): string
    {
        if (! $document->isLocked()) {
            return $this->render($document);
        }

        $path = $this->archive($document);

        return (string) Storage::disk('local')->get($path);
    }

    /**
     * Uloží archivní PDF vystaveného dokladu do storage/app/private/documents/{rok}/{číslo}.pdf
     * a zapíše `pdf_path` a `pdf_sha256`. Existující soubor se nikdy nepřepíše. Vrací cestu na disku `local`.
     */
    public function archive(Document $document): string
    {
        if (! $document->isLocked()) {
            throw new LogicException('Archivní PDF má jen vystavený doklad.');
        }

        $disk = Storage::disk('local');
        $path = $this->archivePath($document);

        if (! $disk->exists($path)) {
            $disk->put($path, $this->render($document));
        }

        // Otisk souboru pro kontrolu integrity; u staršího archivu bez otisku se doplní ze souboru.
        $hash = $document->pdf_sha256;
        if ($hash === null || $document->pdf_path !== $path) {
            $hash = hash('sha256', (string) $disk->get($path));
            $document->forceFill(['pdf_path' => $path, 'pdf_sha256' => $hash])->saveQuietly();
        }

        return $path;
    }

    public function archivePath(Document $document): string
    {
        $year = $document->issue_date->year ?? now()->year;

        return 'documents/'.$year.'/'.$this->safe((string) $document->number).'.pdf';
    }

    /** Název souboru ke stažení: „Faktura-2026061.pdf“. */
    public function filename(Document $document): string
    {
        $number = $document->number !== null && $document->number !== ''
            ? $this->safe($document->number)
            : 'koncept';

        return 'Faktura-'.$number.'.pdf';
    }

    private function safe(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $value), '_') ?: 'doklad';
    }

    /** @param  array<string, mixed>  $config */
    private function makeMpdf(array $config, Document $document): Mpdf
    {
        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf($config + [
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 14,
            'margin_bottom' => self::MARGIN_BOTTOM_MM,
            'margin_header' => 0,
            'margin_footer' => 9,
            'default_font_size' => 9.5,
            'tempDir' => $tempDir,
        ]);

        $supplier = $document->supplier_snapshot['name'] ?? CompanyProfile::current()->name ?? 'Splatno';
        $mpdf->SetTitle('Faktura '.($document->number ?? 'koncept'));
        $mpdf->SetAuthor((string) $supplier);
        $mpdf->SetCreator('Splatno');
        $mpdf->shrink_tables_to_fit = 0;

        return $mpdf;
    }

    /** @param  array{body: string, mono: string, display: string}  $families */
    private function view(Document $document, array $families): string
    {
        return view('pdf.invoice', $this->data($document, $families))->render();
    }

    /**
     * @param  array{body: string, mono: string, display: string}  $families
     * @return array<string, mixed>
     */
    private function data(Document $document, array $families): array
    {
        $draft = ! $document->isLocked();

        if ($draft) {
            $supplier = CompanyProfile::current()?->toSnapshot() ?? [];
            $buyer = $document->contact?->toSnapshot() ?? [];
            $bank = ($document->bankAccount ?? BankAccount::default())?->toSnapshot();
        } else {
            $supplier = $document->supplier_snapshot ?? [];
            $buyer = $document->buyer_snapshot ?? [];
            $bank = $document->bank_account_snapshot;
        }

        $method = $document->payment_method ?? PaymentMethod::BankTransfer;
        $showBank = $method === PaymentMethod::BankTransfer && $bank !== null;

        $currency = $document->currency ?: 'CZK';
        $symbol = $currency === 'CZK' ? 'Kč' : $currency;

        $discount = (float) $document->discount_percent;
        $rounding = (float) $document->rounding;

        return [
            'f' => $families,
            'bottom' => self::MARGIN_BOTTOM_MM,
            'draft' => $draft,
            'number' => $draft ? 'Koncept' : $document->number,
            'supplier' => $this->party($supplier),
            'buyer' => $this->party($buyer),
            'initials' => $this->initials((string) ($supplier['name'] ?? 'AF')),
            'meta' => $this->meta($document, $method),
            'items' => $document->items->map(fn (DocumentItem $item): array => [
                'name' => $item->name,
                'description' => $item->description,
                'qty' => $this->quantity($item->qty),
                'unit' => $item->unit,
                'price' => $this->decimal($item->unit_price),
                'total' => $this->decimal($item->line_total),
            ])->all(),
            'textBefore' => $document->text_before,
            'textAfter' => $document->text_after,
            'bank' => $showBank ? [
                'account' => ($bank['account_number'] ?? '').'/'.($bank['bank_code'] ?? ''),
                'iban' => ! empty($bank['iban']) ? $this->ibans->pretty((string) $bank['iban']) : null,
                'swift' => $bank['swift'] ?? null,
                'vs' => $document->vs,
                'ks' => $document->ks,
            ] : null,
            'qr' => $showBank && ! $draft ? $this->qrDataUri($document) : null,
            'discount' => $discount > 0 ? $this->plain($discount).' %' : null,
            'rounding' => abs($rounding) > 0 ? $this->decimal($rounding) : null,
            'total' => $this->decimal($document->total),
            'totalSize' => $this->totalFontSize($this->decimal($document->total).' '.$symbol),
            'symbol' => $symbol,
            'note' => trim(implode(' ', array_filter([
                $supplier['registration_note'] ?? null,
                ($supplier['vat_payer'] ?? false) ? null : 'Nejsem plátce DPH.',
            ]))),
        ];
    }

    /**
     * Velikost písma celkové částky v zeleném poli: 22 pt, u dlouhých částek menší, aby se
     * vešla na jeden řádek (pole je 62 mm široké, text zhruba 0,6 em na znak).
     */
    private function totalFontSize(string $text): int
    {
        return max(14, min(22, (int) floor(147 / (mb_strlen($text) * 0.6))));
    }

    /** QR Platba jako SVG v data URI; bez QR, když se z dokladu nedá sestavit (např. už je uhrazený). */
    private function qrDataUri(Document $document): ?string
    {
        try {
            return 'data:image/svg+xml;base64,'.base64_encode($this->qr->forDocument($document));
        } catch (InvalidPaymentDataException) {
            return null;
        }
    }

    /**
     * Pruh s daty dokladu. DUZP jen když ho uživatel chce uvést.
     *
     * @return list<array{label: string, value: string, strong: bool, mono: bool}>
     */
    private function meta(Document $document, PaymentMethod $method): array
    {
        $cells = [
            ['Datum vystavení', $this->date($document->issue_date), false, false],
            ['Datum splatnosti', $this->date($document->due_date), true, false],
        ];

        if ($document->show_taxable_date && $document->taxable_date !== null) {
            $cells[] = ['Datum zdan. plnění', $this->date($document->taxable_date), false, false];
        }
        if ($document->vs) {
            $cells[] = ['Variabilní symbol', $document->vs, false, true];
        }
        $cells[] = ['Způsob úhrady', self::PAYMENT_LABELS[$method->value], false, false];
        if ($document->order_number) {
            $cells[] = ['Objednávka č.', $document->order_number, false, false];
        }

        return array_map(
            fn (array $cell): array => ['label' => $cell[0], 'value' => $cell[1], 'strong' => $cell[2], 'mono' => $cell[3]],
            $cells,
        );
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array{name: string, lines: list<string>, ids: string, contact: string, web: string}
     */
    private function party(array $party): array
    {
        $ids = [];
        if (! empty($party['ico'])) {
            $ids[] = 'IČ '.$party['ico'];
        }
        if (! empty($party['dic'])) {
            $ids[] = 'DIČ '.$party['dic'];
        }

        return [
            'name' => (string) ($party['name'] ?? ''),
            'lines' => array_values(array_filter([
                $party['street'] ?? null,
                trim(($party['zip'] ?? '').' '.($party['city'] ?? '')),
            ])),
            'ids' => implode(' · ', $ids),
            'contact' => implode(' · ', array_filter([$party['email'] ?? null, $party['phone'] ?? null])),
            'web' => (string) ($party['web'] ?? ''),
        ];
    }

    private function initials(string $name): string
    {
        $letters = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');

        return $letters !== '' ? $letters : 'AF';
    }

    private function date(?\DateTimeInterface $date): string
    {
        return $date?->format('j. n. Y') ?? '';
    }

    /** Částka s haléři a pevnou mezerou v tisících: „17 900,00“. */
    private function decimal(string|float|null $value): string
    {
        return number_format((float) $value, 2, ',', "\u{00A0}");
    }

    private function plain(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', "\u{00A0}"), '0'), ',');
    }

    /** Počet: aspoň dvě desetinná místa, víc jen když je potřeba („1,00“, „2,5“ → „2,50“, „0,125“). */
    private function quantity(string|float|null $value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 4, ',', "\u{00A0}"), '0'), ',');
        $decimals = str_contains($text, ',') ? strlen(substr($text, (int) strpos($text, ',') + 1)) : 0;

        return $decimals >= 2 ? $text : number_format((float) $value, 2, ',', "\u{00A0}");
    }
}
