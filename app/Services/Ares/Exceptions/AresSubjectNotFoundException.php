<?php

namespace App\Services\Ares\Exceptions;

final class AresSubjectNotFoundException extends AresException
{
    public static function for(string $ico): self
    {
        return new self("Subjekt s IČO {$ico} v ARES neexistuje.");
    }
}
