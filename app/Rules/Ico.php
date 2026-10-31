<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** České IČO: přesně 8 číslic a kontrolní číslice podle modulo 11. */
final class Ico implements ValidationRule
{
    public static function isValid(string $ico): bool
    {
        if (preg_match('/^\d{8}$/', $ico) !== 1) {
            return false;
        }

        $sum = 0;
        foreach (range(0, 6) as $i) {
            $sum += (int) $ico[$i] * (8 - $i);
        }

        return (11 - $sum % 11) % 10 === (int) $ico[7];
    }

    /** Odstraní mezery, které lidé do IČO píšou po trojicích („270 82 440“). */
    public static function normalize(string $ico): string
    {
        return preg_replace('/\s+/', '', $ico) ?? $ico;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid(self::normalize($value))) {
            $fail('Zadejte platné IČO (8 číslic s kontrolním součtem).');
        }
    }
}
