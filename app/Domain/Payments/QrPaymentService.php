<?php

namespace App\Domain\Payments;

use App\Domain\Documents\DocumentType;
use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use App\Models\Document;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/** QR Platba pro PDF faktury: SPAYD z dokladu zakódovaný do SVG (korekce M). */
final class QrPaymentService
{
    public const SIZE = 300;

    public function __construct(
        private readonly SpaydFormatter $spayd = new SpaydFormatter,
        private readonly IbanConverter $ibans = new IbanConverter,
    ) {}

    /**
     * SVG s QR platbou na zbývající částku dokladu.
     *
     * @throws InvalidPaymentDataException
     */
    public function forDocument(Document $document): string
    {
        $result = (new Builder(
            writer: new SvgWriter,
            data: $this->payload($document),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::SIZE,
            margin: 0,
        ))->build();

        return $result->getString();
    }

    /**
     * Text SPAYD, který QR kód nese. Účet se bere ze snapshotu na dokladu (u vystaveného
     * dokladu se už nemění), jinak z přiřazeného účtu.
     *
     * @throws InvalidPaymentDataException
     */
    public function payload(Document $document): string
    {
        if (! $document->type->isPayable()) {
            throw new InvalidPaymentDataException('QR platbu lze vytvořit jen k faktuře nebo záloze.');
        }

        $remaining = $document->remainingAmount();
        if (! $remaining->isPositive()) {
            throw new InvalidPaymentDataException('Doklad nemá nic k úhradě.');
        }

        $account = $document->bank_account_snapshot ?? $document->bankAccount?->toSnapshot();
        if ($account === null) {
            throw new InvalidPaymentDataException('Doklad nemá bankovní účet.');
        }

        $iban = is_string($account['iban'] ?? null) && $account['iban'] !== ''
            ? $account['iban']
            : $this->ibans->fromCzechAccount((string) ($account['account_number'] ?? ''), (string) ($account['bank_code'] ?? ''));

        return $this->spayd->format(
            iban: $iban,
            amount: $remaining,
            currency: $document->currency,
            variableSymbol: $document->vs,
            constantSymbol: $document->ks,
            dueDate: $document->due_date,
            message: $this->messagePrefix($document->type).' '.$document->number,
            bic: is_string($account['swift'] ?? null) ? $account['swift'] : null,
        );
    }

    /** Text zprávy pro příjemce podle typu dokladu. */
    private function messagePrefix(DocumentType $type): string
    {
        return $type === DocumentType::Proforma ? 'ZALOHA' : 'FAKTURA';
    }
}
