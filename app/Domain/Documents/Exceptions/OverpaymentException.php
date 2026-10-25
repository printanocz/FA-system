<?php

namespace App\Domain\Documents\Exceptions;

class OverpaymentException extends DocumentException
{
    public static function exceeds(string $amount, string $remaining): self
    {
        return new self("Úhrada {$amount} přesahuje zbývající částku {$remaining}. Přeplatek není povolený.");
    }
}
