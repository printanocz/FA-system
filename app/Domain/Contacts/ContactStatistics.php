<?php

namespace App\Domain\Contacts;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\Contact;
use App\Models\Document;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Obrat, nezaplacené a průměrná doba úhrady kontaktu. Počítají se jen z faktur (`invoice`):
 * koncepty, stornované doklady, zálohové faktury, dobropisy a nabídky se nezapočítávají.
 * Částky jsou v měně dokladu bez přepočtu (kurzy přijdou s T-25).
 */
final class ContactStatistics
{
    /** Stavy vystavené faktury, která se počítá do obratu. */
    public const ISSUED_STATUSES = [
        DocumentStatus::Issued,
        DocumentStatus::Sent,
        DocumentStatus::PartiallyPaid,
        DocumentStatus::Paid,
    ];

    /** Stavy, ve kterých faktura ještě čeká na úhradu. */
    public const OPEN_STATUSES = [
        DocumentStatus::Issued,
        DocumentStatus::Sent,
        DocumentStatus::PartiallyPaid,
    ];

    /**
     * Rozsah dat pro SQL dotaz na obrat v roce (sloupec `issue_date`).
     *
     * @return array{0: string, 1: string}
     */
    public static function yearRange(int $year): array
    {
        return [sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)];
    }

    public function forContact(Contact $contact, ?CarbonInterface $today = null): ContactStats
    {
        $today ??= Carbon::today();
        $year = $today->year;

        $invoices = Document::query()
            ->where('contact_id', $contact->id)
            ->where('type', DocumentType::Invoice)
            ->whereIn('status', [...self::ISSUED_STATUSES])
            ->get(['id', 'status', 'issue_date', 'due_date', 'paid_at', 'total', 'paid_total']);

        $turnover = BigDecimal::zero();
        $count = 0;
        $unpaid = BigDecimal::zero();
        $nextDue = null;
        $paidDays = [];

        foreach ($invoices as $invoice) {
            if ($invoice->issue_date !== null && $invoice->issue_date->year === $year) {
                $turnover = $turnover->plus($invoice->total);
                $count++;
            }

            if (in_array($invoice->status, self::OPEN_STATUSES, true)) {
                $remaining = $invoice->remainingAmount();

                if ($remaining->isPositive()) {
                    $unpaid = $unpaid->plus($remaining);

                    if ($invoice->due_date !== null) {
                        $due = $invoice->due_date->toDateString();
                        $nextDue = $nextDue === null || $due < $nextDue ? $due : $nextDue;
                    }
                }
            }

            if ($invoice->status === DocumentStatus::Paid && $invoice->paid_at !== null && $invoice->issue_date !== null) {
                $paidDays[] = max(0, (int) $invoice->issue_date->diffInDays($invoice->paid_at, true));
            }
        }

        $average = $paidDays === []
            ? null
            : BigDecimal::of(array_sum($paidDays))->dividedBy(count($paidDays), 0, RoundingMode::HalfUp)->toInt();

        return new ContactStats(
            year: $year,
            turnover: (string) $turnover->toScale(2, RoundingMode::HalfUp),
            invoiceCount: $count,
            unpaid: (string) $unpaid->toScale(2, RoundingMode::HalfUp),
            unpaidNextDue: $nextDue,
            averagePaymentDays: $average,
        );
    }
}
