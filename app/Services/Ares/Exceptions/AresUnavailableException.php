<?php

namespace App\Services\Ares\Exceptions;

final class AresUnavailableException extends AresException
{
    public static function because(string $reason): self
    {
        return new self("ARES je nedostupný: {$reason}");
    }
}
