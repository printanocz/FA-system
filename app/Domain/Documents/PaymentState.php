<?php

namespace App\Domain\Documents;

use App\Models\Document;
use Brick\Math\BigDecimal;

/**
 * Stav úhrad dokladu odvozený ze seznamu úhrad: paid_total, paid_at a uložený stav.
 * Jediné místo, které tyto tři pole po změně úhrad přepočítává (zápis i smazání úhrady).
 */
final class PaymentState
{
    public static function sync(Document $document): void
    {
        $paidTotal = BigDecimal::zero()->toScale(2);
        $lastPaidAt = null;

        foreach ($document->payments()->get() as $payment) {
            $paidTotal = $paidTotal->plus(BigDecimal::of($payment->amount));
            $lastPaidAt = $payment->paid_at;
        }

        $fullyPaid = $paidTotal->isGreaterThanOrEqualTo(BigDecimal::of($document->total));

        $document->forceFill([
            'paid_total' => (string) $paidTotal,
            'paid_at' => $fullyPaid ? $lastPaidAt : null,
            'status' => match (true) {
                $fullyPaid => DocumentStatus::Paid,
                $paidTotal->isPositive() => DocumentStatus::PartiallyPaid,
                // Bez úhrad se doklad vrací do stavu před nimi: odeslaný, jinak vystavený.
                in_array($document->status, [DocumentStatus::PartiallyPaid, DocumentStatus::Paid], true) => $document->sent_at !== null
                    ? DocumentStatus::Sent
                    : DocumentStatus::Issued,
                default => $document->status,
            },
        ])->save();
    }
}
