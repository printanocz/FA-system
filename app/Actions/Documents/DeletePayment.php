<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Domain\Documents\PaymentState;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Smaže poslední úhradu dokladu (podle data úhrady a pořadí) a přepočítá paid_total,
 * paid_at i stav. Starší úhrady se mažou postupně od konce, aby součet zůstal průkazný.
 */
final class DeletePayment
{
    public function __invoke(Payment $payment): Document
    {
        return DB::transaction(function () use ($payment): Document {
            // Zámek dokladu: souběžný zápis nebo smazání úhrady se nesmí přepsat.
            $document = Document::query()->lockForUpdate()->findOrFail($payment->document_id);

            if ($document->status === DocumentStatus::Cancelled) {
                throw new InvalidDocumentStateException('U stornovaného dokladu nelze úhradu smazat.');
            }

            $last = $document->payments()->get()->last();
            if ($last === null || $last->getKey() !== $payment->getKey()) {
                throw new InvalidDocumentStateException('Smazat jde jen poslední úhrada. Nejdřív smažte novější.');
            }

            $payment->delete();
            PaymentState::sync($document);

            return $document;
        });
    }
}
