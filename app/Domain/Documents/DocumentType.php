<?php

namespace App\Domain\Documents;

enum DocumentType: string
{
    case Invoice = 'invoice';
    case Proforma = 'proforma';
    case CreditNote = 'credit_note';
    case Quote = 'quote';

    public function isQuote(): bool
    {
        return $this === self::Quote;
    }

    /** Doklady, které se platí a mají splatnost (všechno kromě nabídky). */
    public function isPayable(): bool
    {
        return $this === self::Invoice || $this === self::Proforma;
    }
}
