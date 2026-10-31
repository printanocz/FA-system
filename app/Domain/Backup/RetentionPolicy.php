<?php

namespace App\Domain\Backup;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Retence záloh: nechá poslední zálohu každého z posledních `daily` dní a poslední zálohu
 * každého z posledních `monthly` měsíců (10 let archivace dokladů), ostatní jsou k smazání.
 * Soubory, jejichž jméno nejde přečíst (`splatno-YYYYMMDD-HHMMSS.zip.enc`), se nikdy nemažou.
 */
final class RetentionPolicy
{
    public function __construct(private readonly int $daily = 14, private readonly int $monthly = 120) {}

    public static function fromConfig(): self
    {
        return new self((int) config('backup.keep_daily'), (int) config('backup.keep_monthly'));
    }

    public static function filename(CarbonInterface $at): string
    {
        return 'splatno-'.$at->format('Ymd-His').'.zip.enc';
    }

    public static function parse(string $filename): ?CarbonImmutable
    {
        if (preg_match('/^splatno-(\d{8})-(\d{6})\.zip\.enc$/', $filename, $m) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Ymd-His', $m[1].'-'.$m[2]);
    }

    /**
     * @param  list<string>  $filenames
     * @return list<string> soubory ke smazání
     */
    public function expired(array $filenames, CarbonInterface $now): array
    {
        $parsed = [];
        foreach ($filenames as $filename) {
            $at = self::parse($filename);
            if ($at !== null) {
                $parsed[$filename] = $at;
            }
        }

        // Od nejnovější: první záloha dne, resp. měsíce, je ta „poslední“, kterou držíme.
        uasort($parsed, fn (CarbonImmutable $a, CarbonImmutable $b): int => $b <=> $a);

        $today = CarbonImmutable::instance($now)->startOfDay();
        $firstDay = $today->subDays($this->daily - 1);
        $firstMonth = $today->startOfMonth()->subMonths($this->monthly - 1);

        $keep = [];
        $seenDays = [];
        $seenMonths = [];
        foreach ($parsed as $filename => $at) {
            $day = $at->format('Y-m-d');
            $month = $at->format('Y-m');

            if ($at->startOfDay() >= $firstDay && ! isset($seenDays[$day])) {
                $seenDays[$day] = true;
                $keep[$filename] = true;
            }
            if ($at->startOfMonth() >= $firstMonth && ! isset($seenMonths[$month])) {
                $seenMonths[$month] = true;
                $keep[$filename] = true;
            }
        }

        return array_values(array_filter(array_keys($parsed), fn (string $filename): bool => ! isset($keep[$filename])));
    }
}
