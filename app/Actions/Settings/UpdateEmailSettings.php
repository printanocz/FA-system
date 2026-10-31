<?php

namespace App\Actions\Settings;

use App\Domain\Mail\EmailSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Uloží šablony e-mailů a odesílatele z Nastavení. Nezadané klíče zůstanou beze změny. */
final class UpdateEmailSettings
{
    public function __construct(private readonly EmailSettings $settings) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool|string>
     *
     * @throws ValidationException
     */
    public function __invoke(array $input): array
    {
        foreach (['sender_name', 'invoice_subject', 'invoice_body', 'reminder_subject', 'reminder_body'] as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]);
            }
        }

        /** @var array<string, bool|string|null> $data */
        $data = Validator::make($input, [
            'sender_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'copy_to_me' => ['sometimes', 'boolean'],
            'invoice_subject' => ['sometimes', 'required', 'string', 'max:200'],
            'invoice_body' => ['sometimes', 'required', 'string', 'max:5000'],
            'reminder_subject' => ['sometimes', 'required', 'string', 'max:200'],
            'reminder_body' => ['sometimes', 'required', 'string', 'max:5000'],
        ], attributes: [
            'sender_name' => 'jméno odesílatele',
            'invoice_subject' => 'předmět faktury',
            'invoice_body' => 'text faktury',
            'reminder_subject' => 'předmět upomínky',
            'reminder_body' => 'text upomínky',
        ])->validate();

        if (array_key_exists('copy_to_me', $data)) {
            $data['copy_to_me'] = (bool) $data['copy_to_me'];
        }
        if (array_key_exists('sender_name', $data)) {
            $data['sender_name'] = (string) $data['sender_name'];
        }

        $this->settings->put($data);

        return $this->settings->all();
    }
}
