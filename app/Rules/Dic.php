<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** České DIČ: „CZ“ a 8 až 10 číslic (právnické osoby 8, fyzické 9–10). */
final class Dic implements ValidationRule
{
    public static function isValid(string $dic): bool
    {
        return preg_match('/^CZ\d{8,10}$/', $dic) === 1;
    }

    public static function normalize(string $dic): string
    {
        return strtoupper(preg_replace('/\s+/', '', $dic) ?? $dic);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid(self::normalize($value))) {
            $fail('Zadejte platné DIČ ve tvaru CZ a 8 až 10 číslic.');
        }
    }
}
