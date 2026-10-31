<?php

namespace App\Domain\Documents\Exceptions;

class DocumentLockedException extends DocumentException
{
    /** @param  list<string>  $fields */
    public static function forFields(array $fields): self
    {
        return new self(
            'Vystavený doklad je zamčený, nelze měnit: '.implode(', ', $fields).'.'
        );
    }

    public static function forItems(): self
    {
        return new self('Vystavený doklad je zamčený, jeho položky nelze měnit.');
    }
}
