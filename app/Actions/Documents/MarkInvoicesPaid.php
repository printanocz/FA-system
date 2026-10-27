<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentException;
use App\Domain\Documents\PaymentMethod;
use App\Models\Document;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hromadné „Označit jako uhrazené“: každá vybraná faktura dostane úhradu na zbývající částku
 * k danému dni (výchozí je dnes). Neplatné (koncept, už uhrazená, storno, jiný typ) přeskočí.
 */
final class MarkInvoicesPaid
{
    public function __construct(private readonly RecordPayment $record) {}

    /**
     * @param  array<int, int|string>  $ids
     * @return array{paid: int, skipped: int}
     */
    public function __invoke(array $ids, ?CarbonInterface $date = null): array
    {
        $date ??= Carbon::today();
        $paid = 0;
        $skipped = 0;

        $documents = Document::query()
            ->where('type', DocumentType::Invoice)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $document = $documents->get($id);

            if ($document === null || ! $this->isPayable($document)) {
                $skipped++;

                continue;
            }

            try {
                ($this->record)($document, (string) $document->remainingAmount(), $date, PaymentMethod::BankTransfer);
                $paid++;
            } catch (DocumentException) {
                $skipped++;
            }
        }

        return ['paid' => $paid, 'skipped' => $skipped];
    }

    private function isPayable(Document $document): bool
    {
        return in_array($document->status, [DocumentStatus::Issued, DocumentStatus::Sent, DocumentStatus::PartiallyPaid], true)
            && $document->remainingAmount()->isPositive();
    }
}
