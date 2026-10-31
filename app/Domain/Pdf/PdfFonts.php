<?php

namespace App\Domain\Pdf;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * Písma dokladu. Když jsou v resources/fonts brandové soubory (DMSans-*.ttf, DMMono-Regular.ttf,
 * CabinetGrotesk-Bold.ttf), zaregistrují se; chybějící písmo nahradí dejavusans (umí češtinu).
 * Varování o chybějících fontech se do logu zapíše jen jednou za den.
 */
final class PdfFonts
{
    public const FALLBACK = 'dejavusans';

    public const FALLBACK_MONO = 'dejavusansmono';

    private readonly string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = rtrim($dir ?? resource_path('fonts'), '/\\');
    }

    /**
     * Nastavení pro mPDF a názvy rodin pro šablonu.
     *
     * @return array{config: array<string, mixed>, families: array{body: string, mono: string, display: string}}
     */
    public function resolve(): array
    {
        $fontData = [];
        $missing = [];

        $body = $this->register($fontData, 'dmsans', [
            'R' => 'DMSans-Regular.ttf',
            'B' => 'DMSans-Bold.ttf',
            'I' => 'DMSans-Italic.ttf',
            'BI' => 'DMSans-BoldItalic.ttf',
        ], 'R');
        $mono = $this->register($fontData, 'dmmono', ['R' => 'DMMono-Regular.ttf'], 'R');
        $display = $this->register($fontData, 'cabinetgrotesk', [
            'R' => 'CabinetGrotesk-Bold.ttf',
            'B' => 'CabinetGrotesk-Bold.ttf',
        ], 'R');

        foreach (['DM Sans' => $body, 'DM Mono' => $mono, 'Cabinet Grotesk' => $display] as $name => $family) {
            if ($family === null) {
                $missing[] = $name;
            }
        }
        $this->warnOnce($missing);

        $body ??= self::FALLBACK;
        $mono ??= self::FALLBACK_MONO;
        $display ??= $body;

        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $defaults = (new FontVariables)->getDefaults()['fontdata'];

        return [
            'config' => [
                'fontDir' => array_merge([$this->dir], $fontDirs),
                'fontdata' => $defaults + $fontData,
                'default_font' => $body,
                'useSubstitutions' => true,
                'backupSubsFont' => [self::FALLBACK],
            ],
            'families' => ['body' => $body, 'mono' => $mono, 'display' => $display],
        ];
    }

    /**
     * Zaregistruje rodinu, když existuje její základní řez. Chybějící tučný, kurzíva apod.
     * se nahradí základním řezem. Vrací název rodiny, nebo `null`.
     *
     * @param  array<string, array<string, string>>  $fontData
     * @param  array<string, string>  $files
     */
    private function register(array &$fontData, string $family, array $files, string $base): ?string
    {
        if (! is_file($this->dir.'/'.$files[$base])) {
            return null;
        }

        foreach ($files as $style => $file) {
            $fontData[$family][$style] = is_file($this->dir.'/'.$file) ? $file : $files[$base];
        }

        return $family;
    }

    /** @param  list<string>  $missing */
    private function warnOnce(array $missing): void
    {
        if ($missing === []) {
            return;
        }

        try {
            if (! Cache::add('pdf.fonts.missing-warned', true, now()->addDay())) {
                return;
            }
        } catch (\Throwable) {
            // Bez cache se varování může zopakovat, PDF to neovlivní.
        }

        Log::warning('Chybí brandová písma pro PDF ('.implode(', ', $missing).') v resources/fonts, použije se dejavusans.');
    }
}
