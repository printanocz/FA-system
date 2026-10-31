<?php

namespace App\Domain\Contacts;

/** Čísla na kartě kontaktu. Částky jsou řetězce s dvěma desetinnými místy (bez zaokrouhlovacích chyb float). */
final readonly class ContactStats
{
    public function __construct(
        public int $year,
        public string $turnover,
        public int $invoiceCount,
        public string $unpaid,
        public ?string $unpaidNextDue,
        public ?int $averagePaymentDays,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'turnover' => $this->turnover,
            'invoice_count' => $this->invoiceCount,
            'unpaid' => $this->unpaid,
            'unpaid_next_due' => $this->unpaidNextDue,
            'average_payment_days' => $this->averagePaymentDays,
        ];
    }
}
