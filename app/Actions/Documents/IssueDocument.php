<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Domain\Documents\NumberSeriesService;
use App\Domain\Documents\PaymentMethod;
use App\Domain\Settings\DocumentSettings;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\NumberSeries;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Vystavení dokladu: přidělí číslo, VS (číslo bez písmen), uloží snapshot dodavatele,
 * odběratele a účtu, přepočítá částky a doklad zamkne (locked_at).
 */
final class IssueDocument
{
    public function __construct(
        private readonly NumberSeriesService $numbers,
        private readonly RecalculateDocument $recalculate,
        private readonly DocumentSettings $settings,
    ) {}

    public function __invoke(Document $document, ?CarbonInterface $issueDate = null): Document
    {
        return DB::transaction(function () use ($document, $issueDate): Document {
            // Zámek řádku brání dvojímu vystavení téhož konceptu souběžně.
            Document::query()->lockForUpdate()->findOrFail($document->getKey());
            $document->refresh();

            if ($document->isLocked() || $document->status !== DocumentStatus::Draft) {
                throw new InvalidDocumentStateException('Vystavit lze jen koncept.');
            }

            if (! $document->items()->exists()) {
                throw new InvalidDocumentStateException('Doklad bez položek nelze vystavit.');
            }

            $contact = $document->contact
                ?? throw new InvalidDocumentStateException('Doklad nemá odběratele.');

            $company = CompanyProfile::current()
                ?? throw new InvalidDocumentStateException('Chybí údaje o firmě (dodavateli).');

            $series = $document->series ?? NumberSeries::defaultFor($document->type)
                ?? throw new InvalidDocumentStateException('Pro tento typ dokladu neexistuje číselná řada.');

            $needsAccount = ! $document->type->isQuote()
                && $document->payment_method === PaymentMethod::BankTransfer;
            $account = $document->bankAccount ?? BankAccount::default();
            if ($needsAccount && $account === null) {
                throw new InvalidDocumentStateException('Doklad s platbou převodem nemá bankovní účet.');
            }

            $issued = Carbon::instance($issueDate ?? $document->issue_date ?? Carbon::today())->startOfDay();
            $allocated = $this->numbers->allocate($series, $issued);

            $document->issue_date = $issued;
            $document->taxable_date ??= $issued;

            if ($document->type->isQuote()) {
                $document->valid_until ??= $issued->copy()->addDays($this->settings->quoteValidityDays());
            } else {
                $dueDays = $this->settings->dueDaysFor($contact);
                $document->due_date ??= $issued->copy()->addDays($dueDays);
                $document->vs ??= preg_replace('/\D+/', '', $allocated->number);
            }

            $document->series_id = $series->getKey();
            $document->setRelation('series', $series);
            $document->number = $allocated->number;
            $document->number_year = $allocated->year;
            $document->number_seq = $allocated->sequence;

            $document->buyer_snapshot = $contact->toSnapshot();
            $document->supplier_snapshot = $company->toSnapshot();
            $document->bank_account_snapshot = $account?->toSnapshot();
            $document->bank_account_id ??= $account?->getKey();

            ($this->recalculate)($document, $company->vat_payer, save: false);

            $document->status = DocumentStatus::issuedFor($document->type);
            $document->locked_at = Carbon::now();
            $document->save();

            return $document;
        });
    }
}
