<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Domain\Documents\Exceptions\OverpaymentException;
use App\Domain\Documents\PaymentMethod;
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

            $this->syncPaymentState($document);

            return $payment;
        });
    }

    /** Přepočítá paid_total, paid_at a stav podle všech úhrad dokladu. */
    private function syncPaymentState(Document $document): void
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
                default => $document->status,
            },
        ])->save();
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
