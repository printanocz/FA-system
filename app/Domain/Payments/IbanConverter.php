<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Exceptions\InvalidPaymentDataException;

/** Převod českého čísla účtu (předčíslí-číslo/kód banky) na IBAN a validace IBAN. */
final class IbanConverter
{
    /**
     * @param  string  $accountNumber  „2401234567“ nebo „19-2000145399“ (bez „/kód banky“)
     * @param  string  $bankCode  čtyřmístný kód banky, např. „0800“
     *
     * @throws InvalidPaymentDataException
     */
    public function fromCzechAccount(string $accountNumber, string $bankCode): string
    {
        $accountNumber = preg_replace('/\s+/', '', $accountNumber) ?? $accountNumber;
        $bankCode = trim($bankCode);

        if (preg_match('/^(?:(\d{1,6})-)?(\d{2,10})$/', $accountNumber, $match) !== 1) {
            throw new InvalidPaymentDataException("Neplatné číslo účtu „{$accountNumber}“.");
        }

        if (preg_match('/^\d{4}$/', $bankCode) !== 1) {
            throw new InvalidPaymentDataException("Neplatný kód banky „{$bankCode}“.");
        }

        $bban = $bankCode
            .str_pad($match[1], 6, '0', STR_PAD_LEFT)
            .str_pad($match[2], 10, '0', STR_PAD_LEFT);

        // Kontrolní číslice: 98 − (BBAN + „CZ00“ jako čísla, C=12, Z=35) mod 97.
        $check = 98 - $this->mod97($bban.'123500');

        return 'CZ'.str_pad((string) $check, 2, '0', STR_PAD_LEFT).$bban;
    }

    /** Platný IBAN = správný tvar a kontrolní součet modulo 97 (mezery a malá písmena se ignorují). */
    public function isValid(string $iban): bool
    {
        $iban = $this->normalize($iban);

        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        return $this->mod97($numeric) === 1;
    }

    /** IBAN bez mezer, velkými písmeny. */
    public function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? $iban);
    }

    /** IBAN po čtveřicích pro tisk („CZ15 0800 0000 0024 0123 4567“). */
    public function pretty(string $iban): string
    {
        return trim(chunk_split($this->normalize($iban), 4, ' '));
    }

    /** Modulo 97 nad libovolně dlouhým číselným řetězcem po kouscích (bez bcmath). */
    private function mod97(string $digits): int
    {
        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }
}
