<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Domain\Documents\Exceptions\OverpaymentException;
use App\Domain\Documents\PaymentMethod;
use App\Domain\Documents\PaymentState;
use App\Models\Document;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Zaznamená úhradu (plnou nebo částečnou) a přepočítá paid_total, paid_at a stav.
 * Přeplatek je chyba (OverpaymentException).
 */
final class RecordPayment
{
    public function __invoke(
        Document $document,
        string|int|BigDecimal $amount,
        CarbonInterface|string|null $paidAt = null,
        PaymentMethod $method = PaymentMethod::BankTransfer,
        ?string $note = null,
    ): Payment {
        return DB::transaction(function () use ($document, $amount, $paidAt, $method, $note): Payment {
            // Zámek dokladu: dvě souběžné úhrady se nesmí vzájemně přepsat.
            Document::query()->lockForUpdate()->findOrFail($document->getKey());
            $document->refresh();

            $payable = $document->type->isPayable()
                && in_array($document->status, [
                    DocumentStatus::Issued,
                    DocumentStatus::Sent,
                    DocumentStatus::PartiallyPaid,
                ], true);

            if (! $payable) {
                throw new InvalidDocumentStateException(
                    'Úhradu lze zaznamenat jen u vystavené, odeslané nebo částečně uhrazené faktury.'
                );
            }

            $value = $this->parseAmount($amount);
            $remaining = $document->remainingAmount();

            if ($value->isGreaterThan($remaining)) {
                throw OverpaymentException::exceeds((string) $value, (string) $remaining);
            }

            $payment = $document->payments()->create([
                'amount' => (string) $value,
                'currency' => $document->currency,
                'paid_at' => $paidAt === null ? Carbon::today() : Carbon::parse($paidAt),
                'method' => $method,
                'note' => $note,
            ]);

            PaymentState::sync($document);

            return $payment;
        });
    }

    private function parseAmount(string|int|BigDecimal $amount): BigDecimal
    {
        try {
            $value = BigDecimal::of($amount)->toScale(2, RoundingMode::Unnecessary);
        } catch (MathException) {
            throw new InvalidArgumentException('Částka úhrady musí být číslo s nejvýše dvěma desetinnými místy.');
        }

        if (! $value->isPositive()) {
            throw new InvalidArgumentException('Částka úhrady musí být kladná.');
        }

        return $value;
    }
}
