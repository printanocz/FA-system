<?php

namespace App\Actions\Settings;

use App\Models\CompanyProfile;
use App\Rules\Dic;
use App\Rules\Ico;
use App\Services\Ares\AresClient;
use App\Services\Ares\Exceptions\AresException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Uloží údaje dodavatele (singleton). IČO a DIČ se kontrolují pravidly z T-09a. */
final class UpdateCompanyProfile
{
    public function __construct(private readonly AresClient $ares) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(array $input): CompanyProfile
    {
        $input = $this->normalize($input);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'ico' => ['nullable', new Ico],
            'dic' => ['nullable', new Dic],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'registration_note' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'web' => ['nullable', 'string', 'max:255'],
            'vat_payer' => ['sometimes', 'boolean'],
        ], attributes: [
            'name' => 'název',
            'ico' => 'IČO',
            'dic' => 'DIČ',
            'street' => 'ulice',
            'city' => 'město',
            'zip' => 'PSČ',
            'country' => 'země',
            'registration_note' => 'údaj o zápisu',
            'phone' => 'telefon',
            'web' => 'web',
            'vat_payer' => 'plátce DPH',
        ])->validate();

        if (array_key_exists('vat_payer', $data)) {
            $data['vat_payer'] = (bool) $data['vat_payer'];
        }

        $profile = CompanyProfile::current() ?? new CompanyProfile;
        $profile->fill($data)->save();

        return $profile;
    }

    /**
     * „Načíst z ARES“: vrátí hodnoty pro předvyplnění formuláře, nic neukládá.
     * DIČ je v poli jen tehdy, když ho ARES vrátil, takže formulář vlastní DIČ nesmaže.
     *
     * @return array<string, string>
     *
     * @throws AresException
     */
    public function suggestFromAres(string $ico): array
    {
        $subject = $this->ares->find($ico);

        return array_filter([
            'name' => $subject->name,
            'ico' => $subject->ico,
            'dic' => $subject->dic,
            'street' => $subject->street,
            'city' => $subject->city,
            'zip' => $subject->zip,
            'country' => $subject->country,
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        if (is_string($input['ico'] ?? null)) {
            $input['ico'] = Ico::normalize($input['ico']);
        }
        if (is_string($input['dic'] ?? null)) {
            $input['dic'] = Dic::normalize($input['dic']);
        }
        if (is_string($input['country'] ?? null)) {
            $input['country'] = strtoupper($input['country']);
        }

        return $input;
    }
}
