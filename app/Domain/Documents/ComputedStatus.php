<?php

namespace App\Domain\Documents;

/** Stav odvozený z dat a aktuálního data, nikdy se neukládá. */
enum ComputedStatus: string
{
    case Overdue = 'overdue';
    case Expired = 'expired';
}
