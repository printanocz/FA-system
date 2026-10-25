<?php

namespace App\Actions\Settings;

use App\Domain\Documents\Exceptions\NumberSeriesException;
use App\Domain\Documents\NumberSeriesService;
use App\Models\Document;
use App\Models\NumberSeries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Upraví číselnou řadu (prefix, formát, další číslo, roční reset, výchozí).
 * Další číslo jde nastavit jen dopředu: pokud už řada přidělila čísla, nikdy pod aktuální čítač.
 * Díky tomu jde řada napojit na iDoklad (nastavit na 61 → první faktura 2026061).
 */
final class UpdateNumberSeries
{
    public function __construct(private readonly NumberSeriesService $numbers) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws NumberSeriesException
     */
    public function __invoke(NumberSeries $series, array $input): NumberSeries
    {
        if (array_key_exists('prefix', $input) && is_string($input['prefix'])) {
            $input['prefix'] = trim($input['prefix']);
        }

        $data = Validator::make($input, [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9._\/-]*$/'],
            'format' => ['sometimes', 'required', 'string', 'max:50'],
            'next_number' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999'],
            'reset_yearly' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ], attributes: [
            'name' => 'název',
            'prefix' => 'prefix',
            'format' => 'formát',
            'next_number' => 'další číslo',
        ])->validate();

        if (array_key_exists('prefix', $data)) {
            $data['prefix'] = (string) $data['prefix'];
        }

        if (isset($data['format'])) {
            try {
                $this->numbers->format('', (string) $data['format'], 2026, 1);
            } catch (NumberSeriesException $e) {
                throw ValidationException::withMessages(['format' => $e->getMessage()]);
            }
        }

        return DB::transaction(function () use ($series, $data): NumberSeries {
            $locked = NumberSeries::query()->lockForUpdate()->findOrFail($series->getKey());

            if (isset($data['next_number']) && $data['next_number'] < $locked->next_number) {
                $hasNumbers = Document::query()
                    ->where('series_id', $locked->getKey())
                    ->whereNotNull('number')
                    ->exists();

                if ($hasNumbers) {
                    throw new NumberSeriesException(
                        "Další číslo nelze snížit pod {$locked->next_number}, řada už čísla přidělila."
                    );
                }
            }

            $locked->fill($data)->save();
            $series->setRawAttributes($locked->getAttributes(), true);

            return $series;
        });
    }
}
