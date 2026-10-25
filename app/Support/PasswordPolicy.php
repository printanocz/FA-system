<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Validační pravidla pro nové heslo (vytvoření uživatele, reset hesla).
     *
     * @return list<mixed>
     */
    public static function rules(bool $confirmed = false): array
    {
        $rules = ['required', 'string', Password::min(self::MIN_LENGTH)];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
