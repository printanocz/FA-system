<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Rules\Dic;
use App\Rules\Ico;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Vytvoří nebo upraví kontakt. IČO a DIČ se kontrolují pravidly z T-09a. */
final class SaveContact
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(array $input, ?Contact $contact = null): Contact
    {
        $data = Validator::make($this->normalize($input), [
            'type' => ['required', Rule::in(['company', 'person'])],
            'name' => ['required', 'string', 'max:255'],
            'ico' => ['nullable', new Ico],
            'dic' => ['nullable', new Dic],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'email' => ['nullable', 'email', 'max:255'],
            'email_cc' => ['nullable', 'array', 'max:10'],
            'email_cc.*' => ['email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'default_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'default_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'email_cc.*.email' => 'Kopie: „:input“ není platná e-mailová adresa.',
            'email_cc.max' => 'Do kopie jde nejvýše 10 adres.',
        ], [
            'type' => 'typ',
            'name' => 'název',
            'ico' => 'IČO',
            'dic' => 'DIČ',
            'street' => 'ulice',
            'city' => 'obec',
            'zip' => 'PSČ',
            'country' => 'země',
            'phone' => 'telefon',
            'email_cc' => 'kopie',
            'default_due_days' => 'splatnost',
            'default_discount_percent' => 'sleva',
            'note' => 'poznámka',
        ])->validate();

        $contact ??= new Contact;
        $contact->fill($data)->save();

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        $text = static function (mixed $value): ?string {
            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        $out = [
            'type' => $text($input['type'] ?? null) ?? 'company',
            'name' => $text($input['name'] ?? null),
            'street' => $text($input['street'] ?? null),
            'city' => $text($input['city'] ?? null),
            'zip' => $text($input['zip'] ?? null),
            'country' => Str::upper($text($input['country'] ?? null) ?? 'CZ'),
            'phone' => $text($input['phone'] ?? null),
            'note' => $text($input['note'] ?? null),
            'default_due_days' => $text($input['default_due_days'] ?? null),
            'default_discount_percent' => $text(str_replace(',', '.', (string) ($input['default_discount_percent'] ?? ''))),
        ];

        $ico = $text($input['ico'] ?? null);
        $out['ico'] = $ico === null ? null : Ico::normalize($ico);

        $dic = $text($input['dic'] ?? null);
        $out['dic'] = $dic === null ? null : Dic::normalize($dic);

        $email = $text($input['email'] ?? null);
        $out['email'] = $email === null ? null : Str::lower($email);

        $cc = $input['email_cc'] ?? null;
        $addresses = is_array($cc) ? $cc : preg_split('/[,;\s]+/', (string) $cc);
        $addresses = array_values(array_filter(array_map(
            static fn ($address) => is_string($address) ? Str::lower(trim($address)) : '',
            $addresses === false ? [] : $addresses,
        ), static fn (string $address): bool => $address !== ''));
        $out['email_cc'] = $addresses === [] ? null : $addresses;

        return $out;
    }
}
