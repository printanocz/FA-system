<?php

namespace App\Domain\Documents;

use App\Domain\Documents\Exceptions\NumberSeriesException;
use App\Models\NumberSeries;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Číselné řady dokladů. Číslo se přiděluje až při vystavení (koncept ho nemá),
 * atomicky: transakce + zámek řádku řady (SELECT ... FOR UPDATE).
 */
class NumberSeriesService
{
    /**
     * Přidělí další číslo řady. Rok se bere z data vystavení. U řady s ročním resetem se
     * po změně roku čítač vrátí na 1. Doklad s datem z dřívějšího roku, než ve kterém už
     * se čísla přidělovala, se odmítne (číselná řada nesmí jít zpět).
     */
    public function allocate(NumberSeries $series, CarbonInterface $issueDate): AllocatedNumber
    {
        return DB::transaction(function () use ($series, $issueDate): AllocatedNumber {
            $locked = NumberSeries::query()->lockForUpdate()->findOrFail($series->getKey());
            $year = (int) $issueDate->format('Y');

            if ($locked->reset_yearly && $locked->last_year !== null) {
                if ($year < $locked->last_year) {
                    throw new NumberSeriesException(
                        "Doklad nelze vystavit s datem z roku {$year}, řada už přidělila čísla v roce {$locked->last_year}."
                    );
                }

                if ($year > $locked->last_year) {
                    $locked->next_number = 1;
                }
            }

            $sequence = $locked->next_number;
            $number = $this->format($locked->prefix, $locked->format, $year, $sequence);

            $locked->next_number = $sequence + 1;
            $locked->last_year = max($year, (int) $locked->last_year);
            $locked->save();

            $series->refresh();

            return new AllocatedNumber($number, $year, $sequence);
        });
    }

    /** Číslo, které by řada přidělila příště (pro náhled v nastavení). Nic nemění. */
    public function preview(NumberSeries $series, CarbonInterface $issueDate): string
    {
        $year = (int) $issueDate->format('Y');
        $sequence = $series->next_number;

        if ($series->reset_yearly && $series->last_year !== null && $year > $series->last_year) {
            $sequence = 1;
        }

        return $this->format($series->prefix, $series->format, $year, $sequence);
    }

    /**
     * Vrátí číslo do řady po smazání posledního dokladu, aby v číslování nevznikla díra.
     * Čítač se vrací jen tehdy, když smazané číslo bylo opravdu naposledy přidělené.
     */
    public function release(NumberSeries $series, int $year, int $sequence): void
    {
        DB::transaction(function () use ($series, $year, $sequence): void {
            $locked = NumberSeries::query()->lockForUpdate()->findOrFail($series->getKey());

            if ($locked->last_year === $year && $locked->next_number === $sequence + 1) {
                $locked->next_number = $sequence;
                $locked->save();
            }
        });

        $series->refresh();
    }

    /**
     * Složí číslo z prefixu a formátu. Tokeny: {YYYY} rok, {YY} rok dvoumístně,
     * {N...} pořadí doplněné nulami na počet N (např. {NNN} → 061).
     */
    public function format(string $prefix, string $format, int $year, int $sequence): string
    {
        $count = 0;
        $result = preg_replace_callback(
            '/\{(N+)\}/',
            function (array $match) use ($sequence, &$count): string {
                $count++;

                return str_pad((string) $sequence, strlen($match[1]), '0', STR_PAD_LEFT);
            },
            $format,
        );

        if ($result === null || $count !== 1) {
            throw new NumberSeriesException(
                "Formát číselné řady „{$format}“ musí obsahovat právě jeden token pořadí, např. {NNN}."
            );
        }

        $result = str_replace(
            ['{YYYY}', '{YY}'],
            [sprintf('%04d', $year), sprintf('%02d', $year % 100)],
            $result,
        );

        return $prefix.$result;
    }
}
