<?php

namespace App\Actions\Settings;

use App\Domain\Settings\DocumentSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Uloží výchozí hodnoty dokladů z Nastavení. Nezadané klíče zůstanou beze změny. */
final class UpdateDocumentSettings
{
    public function __construct(private readonly DocumentSettings $settings) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, int|bool|string|null>
     *
     * @throws ValidationException
     */
    public function __invoke(array $input): array
    {
        $input = $this->normalize($input);

        /** @var array<string, int|bool|string|null> $data */
        $data = Validator::make($input, [
            'due_days' => ['sometimes', 'required', 'integer', 'between:0,365'],
            'quote_validity_days' => ['sometimes', 'required', 'integer', 'between:1,365'],
            'constant_symbol' => ['sometimes', 'nullable', 'digits_between:1,10'],
            'round_total' => ['sometimes', 'boolean'],
            'text_before' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'text_after' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], attributes: [
            'due_days' => 'splatnost',
            'quote_validity_days' => 'platnost nabídky',
            'constant_symbol' => 'konstantní symbol',
            'text_before' => 'text před položkami',
            'text_after' => 'text za položkami',
        ])->validate();

        if (array_key_exists('round_total', $data)) {
            $data['round_total'] = (bool) $data['round_total'];
        }
        foreach (['due_days', 'quote_validity_days'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = (int) $data[$key];
            }
        }

        $this->settings->put($data);

        return $this->settings->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        foreach (['constant_symbol', 'text_before', 'text_after'] as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]) === '' ? null : trim($input[$key]);
            }
        }

        return $input;
    }
}
