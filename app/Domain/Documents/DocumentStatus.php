<?php

namespace App\Domain\Documents;

/**
 * Uložený stav dokladu. „Po splatnosti“ a „expirovaná“ se neukládají,
 * počítá je Document::computedStatus().
 *
 * Faktura: draft, issued, sent, partially_paid, paid, cancelled.
 * Nabídka: draft, created, sent, ordered, invoiced, rejected.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Created = 'created';
    case Ordered = 'ordered';
    case Invoiced = 'invoiced';
    case Rejected = 'rejected';

    /** Stav, do kterého doklad přejde při vystavení. */
    public static function issuedFor(DocumentType $type): self
    {
        return $type->isQuote() ? self::Created : self::Issued;
    }

    /** Doklad byl odeslán nebo (částečně) uhrazen, takže ho nelze smazat. */
    public function isSentOrPaid(): bool
    {
        return match ($this) {
            self::Sent, self::PartiallyPaid, self::Paid => true,
            default => false,
        };
    }
}
