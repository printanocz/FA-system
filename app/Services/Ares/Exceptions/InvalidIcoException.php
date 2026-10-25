<?php

namespace App\Services\Ares\Exceptions;

final class InvalidIcoException extends AresException
{
    public static function for(string $ico): self
    {
        return new self("Neplatné IČO „{$ico}“.");
    }
}
