<?php

namespace App\Actions\Documents;

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\PaymentMethod;
use App\Domain\Settings\DocumentSettings;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Support\Carbon;

/**
 * Založí koncept s výchozími hodnotami z Nastavení: splatnost (kontakt má přednost),
 * platnost nabídky, KS, zaokrouhlení a texty před/za položkami. Číslo koncept nemá.
 */
final class CreateDraftDocument
{
    public function __construct(private readonly DocumentSettings $settings) {}

    /**
     * @param  array<string, mixed>  $attributes  přepíše výchozí hodnoty
     */
    public function __invoke(DocumentType $type, ?Contact $contact = null, array $attributes = []): Document
    {
        $issued = Carbon::today();

        $defaults = [
            'type' => $type,
            'status' => DocumentStatus::Draft,
            'contact_id' => $contact?->getKey(),
            'issue_date' => $issued,
            'currency' => 'CZK',
            'language' => 'cs',
            'round_total' => $this->settings->roundTotal(),
            'discount_percent' => $contact->default_discount_percent ?? '0.00',
        ];

        if ($type->isQuote()) {
            $defaults['valid_until'] = $issued->copy()->addDays($this->settings->quoteValidityDays());
        } else {
            $defaults += [
                'payment_method' => PaymentMethod::BankTransfer,
                'due_date' => $issued->copy()->addDays($this->settings->dueDaysFor($contact)),
                'ks' => $this->settings->constantSymbol(),
                'text_before' => $this->settings->textBefore(),
                'text_after' => $this->settings->textAfter(),
            ];
        }

        return Document::query()->create(array_replace($defaults, $attributes));
    }
}
