<?php

namespace App\Domain\Documents\Exceptions;

class DocumentNotDeletableException extends DocumentException
{
    public static function sentOrPaid(): self
    {
        return new self('Odeslaný nebo uhrazený doklad nelze smazat. Použijte storno nebo dobropis.');
    }

    public static function cancelled(): self
    {
        return new self('Stornovaný doklad nelze smazat, je součástí číselné řady.');
    }

    public static function notLastInSeries(): self
    {
        return new self('Smazat jde jen koncept nebo poslední doklad v řadě.');
    }
}
