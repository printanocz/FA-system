<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Formát QR Platba (SPAYD 1.0, specifikace České bankovní asociace):
 * SPD*1.0*ACC:IBAN[+BIC]*AM:částka*CC:měna*X-VS:..*X-KS:..*DT:YYYYMMDD*MSG:text
 */
final class SpaydFormatter
{
    public const MESSAGE_MAX_LENGTH = 60;

    public function __construct(private readonly IbanConverter $ibans = new IbanConverter) {}

    /**
     * @throws InvalidPaymentDataException
     */
    public function format(
        string $iban,
        string|int|BigDecimal $amount,
        string $currency = 'CZK',
        ?string $variableSymbol = null,
        ?string $constantSymbol = null,
        ?DateTimeInterface $dueDate = null,
        ?string $message = null,
        ?string $bic = null,
    ): string {
        if (! $this->ibans->isValid($iban)) {
            throw new InvalidPaymentDataException('Neplatný IBAN příjemce.');
        }

        $account = $this->ibans->normalize($iban);
        $bic = $bic === null ? '' : strtoupper(preg_replace('/\s+/', '', $bic) ?? $bic);
        if ($bic !== '') {
            if (preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic) !== 1) {
                throw new InvalidPaymentDataException('Neplatný BIC příjemce.');
            }
            $account .= '+'.$bic;
        }

        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidPaymentDataException('Měna musí mít tři písmena (ISO 4217).');
        }

        $parts = ['SPD', '1.0', 'ACC:'.$account, 'AM:'.$this->amount($amount), 'CC:'.$currency];

        if ($variableSymbol !== null && $variableSymbol !== '') {
            $parts[] = 'X-VS:'.$this->symbol($variableSymbol, 'variabilní');
        }

        if ($constantSymbol !== null && $constantSymbol !== '') {
            $parts[] = 'X-KS:'.$this->symbol($constantSymbol, 'konstantní');
        }

        if ($dueDate !== null) {
            $parts[] = 'DT:'.$dueDate->format('Ymd');
        }

        $text = $this->message($message);
        if ($text !== '') {
            $parts[] = 'MSG:'.$this->escape($text);
        }

        return implode('*', $parts);
    }

    /**
     * Zpráva pro příjemce: bez diakritiky, velkými písmeny, jen tisknutelné ASCII,
     * nejvýše 60 znaků (ořez se dělá před escapováním, aby nerozťal „%2A“).
     */
    public function message(?string $message): string
    {
        if ($message === null) {
            return '';
        }

        $ascii = strtoupper(Str::ascii($message));
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $ascii) ?? '';
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? '';

        return rtrim(substr(trim($ascii), 0, self::MESSAGE_MAX_LENGTH));
    }

    /** Hvězdička odděluje pole, proto se zapisuje jako %2A; procento jako %25. */
    public function escape(string $value): string
    {
        return str_replace(['%', '*'], ['%25', '%2A'], $value);
    }

    private function amount(string|int|BigDecimal $amount): string
    {
        try {
            $value = BigDecimal::of($amount)->toScale(2, RoundingMode::Unnecessary);
        } catch (MathException) {
            throw new InvalidPaymentDataException('Částka musí být číslo s nejvýše dvěma desetinnými místy.');
        }

        if ($value->isNegative() || $value->isGreaterThan('9999999.99')) {
            throw new InvalidPaymentDataException('Částka QR platby musí být v rozsahu 0 až 9 999 999,99.');
        }

        return (string) $value;
    }

    private function symbol(string $value, string $label): string
    {
        if (preg_match('/^\d{1,10}$/', $value) !== 1) {
            throw new InvalidPaymentDataException("Neplatný {$label} symbol (nejvýše 10 číslic).");
        }

        return $value;
    }
}
