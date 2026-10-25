<?php

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use Illuminate\Support\Carbon;

it('gives a draft no number', function () {
    $draft = draftInvoice();

    expect($draft->number)->toBeNull()
        ->and($draft->status)->toBe(DocumentStatus::Draft)
        ->and($draft->locked_at)->toBeNull();
});

it('assigns the number, the variable symbol and locks the document', function () {
    $issued = issueInvoice(draftInvoice(), '2026-03-10');

    expect($issued->number)->toBe('2026001')
        ->and($issued->vs)->toBe('2026001')
        ->and($issued->status)->toBe(DocumentStatus::Issued)
        ->and($issued->isLocked())->toBeTrue()
        ->and($issued->issue_date->toDateString())->toBe('2026-03-10');
});

it('derives the variable symbol by stripping letters from the number', function () {
    NumberSeries::factory()->create(['prefix' => 'FA', 'format' => '{YY}{NNNN}']);

    $issued = issueInvoice(draftInvoice(), '2026-03-10');

    expect($issued->number)->toBe('FA260001')->and($issued->vs)->toBe('260001');
});

it('numbers invoices one after another', function () {
    $first = issueInvoice(draftInvoice(), '2026-03-10');
    $second = issueInvoice(draftInvoice(), '2026-03-11');

    expect([$first->number, $second->number])->toBe(['2026001', '2026002']);
});

it('computes the totals when issuing', function () {
    $issued = issueInvoice(draftInvoice([[38, 400]]), '2026-03-10');

    expect($issued->subtotal)->toBe('15200.00')
        ->and($issued->vat_total)->toBe('0.00')
        ->and($issued->total)->toBe('15200.00')
        ->and($issued->items()->first()->line_total)->toBe('15200.00');
});

it('issues an invoice of 38 hours at 400 Kč for 15 200,00 Kč', function () {
    $issued = issueInvoice(draftInvoice([[38, 400]]), '2026-03-10');

    expect((string) $issued->total)->toBe('15200.00')->and($issued->remainingAmount()->__toString())->toBe('15200.00');
});

it('sets the due date from the contact default, otherwise 14 days', function () {
    $withDefault = draftInvoice(attributes: ['contact_id' => Contact::factory()->create(['default_due_days' => 30])->id]);
    $without = draftInvoice();

    expect(issueInvoice($withDefault, '2026-03-10')->due_date->toDateString())->toBe('2026-04-09')
        ->and(issueInvoice($without, '2026-03-10')->due_date->toDateString())->toBe('2026-03-24');
});

it('keeps a due date that was set on the draft', function () {
    $draft = draftInvoice(attributes: ['due_date' => '2026-05-01']);

    expect(issueInvoice($draft, '2026-03-10')->due_date->toDateString())->toBe('2026-05-01');
});

it('stores snapshots of supplier, buyer and bank account', function () {
    $draft = draftInvoice();
    $contact = $draft->contact;

    $issued = issueInvoice($draft, '2026-03-10');

    expect($issued->buyer_snapshot['name'])->toBe($contact->name)
        ->and($issued->supplier_snapshot['name'])->toBe(CompanyProfile::current()->name)
        ->and($issued->bank_account_snapshot['account_number'])->toBe(BankAccount::default()->account_number);
});

it('keeps the snapshots when the contact or company change later', function () {
    $draft = draftInvoice();
    $buyerName = $draft->contact->name;
    $supplierName = CompanyProfile::current()->name;
    $issued = issueInvoice($draft, '2026-03-10');

    $draft->contact->update(['name' => 'Přejmenovaná s.r.o.']);
    CompanyProfile::current()->update(['name' => 'Jiný dodavatel']);

    $fresh = $issued->fresh();

    expect($fresh->buyer_snapshot['name'])->toBe($buyerName)
        ->and($fresh->supplier_snapshot['name'])->toBe($supplierName);
});

it('issues a quote with a validity instead of a due date and no variable symbol', function () {
    CompanyProfile::factory()->create();
    NumberSeries::factory()->forType(DocumentType::Quote)->create(['prefix' => 'N']);
    $quote = Document::factory()->quote()->create();
    DocumentItem::factory()->create(['document_id' => $quote->id, 'qty' => '2', 'unit_price' => '50']);

    $issued = issueInvoice($quote, '2026-03-10');

    expect($issued->number)->toBe('N2026001')
        ->and($issued->status)->toBe(DocumentStatus::Created)
        ->and($issued->vs)->toBeNull()
        ->and($issued->due_date)->toBeNull()
        ->and($issued->valid_until->toDateString())->toBe('2026-03-24');
});

it('refuses to issue a document twice', function () {
    $issued = issueInvoice(draftInvoice(), '2026-03-10');

    issueInvoice($issued);
})->throws(InvalidDocumentStateException::class);

it('refuses a document without items', function () {
    issueInvoice(draftInvoice([]));
})->throws(InvalidDocumentStateException::class);

it('refuses to issue without company data', function () {
    $draft = draftInvoice();
    CompanyProfile::current()->delete();

    issueInvoice($draft);
})->throws(InvalidDocumentStateException::class);

it('refuses a bank transfer invoice without a bank account', function () {
    $draft = draftInvoice();
    BankAccount::query()->delete();

    issueInvoice($draft);
})->throws(InvalidDocumentStateException::class);

it('does not consume a number when issuing fails', function () {
    $draft = draftInvoice([]);

    try {
        issueInvoice($draft, '2026-03-10');
    } catch (InvalidDocumentStateException) {
    }

    expect(NumberSeries::defaultFor(DocumentType::Invoice)->next_number)->toBe(1);
});

it('uses the VAT payer flag of the company', function () {
    CompanyProfile::factory()->vatPayer()->create();
    $draft = draftInvoice([[1, 100]]);
    $draft->items()->update(['vat_rate' => '21.00']);

    $issued = issueInvoice($draft, '2026-03-10');

    expect($issued->vat_total)->toBe('21.00')->and($issued->total)->toBe('121.00');
});

it('takes the issue date from today when none is given', function () {
    Carbon::setTestNow('2026-08-15 10:00:00');

    $issued = issueInvoice(draftInvoice());

    expect($issued->number)->toBe('2026001')
        ->and($issued->issue_date->toDateString())->toBe('2026-08-15');

    Carbon::setTestNow();
});
