<?php

use App\Actions\Documents\RecalculateDocument;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\NumberSeries;
use App\Models\Payment;
use App\Models\PriceItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('creates all tables of the domain model', function () {
    foreach (['company_profile', 'bank_accounts', 'number_series', 'contacts', 'price_items', 'documents', 'document_items', 'payments'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }
});

it('stores money as DECIMAL(15,2) and quantity as DECIMAL(15,4)', function () {
    $document = draftInvoice([['1.2345', '9999999999999.99']]);
    $item = $document->items()->first()->fresh();

    expect($item->qty)->toBe('1.2345')->and($item->unit_price)->toBe('9999999999999.99');
});

it('creates every model through its factory', function () {
    expect(CompanyProfile::factory()->create())->toBeInstanceOf(CompanyProfile::class)
        ->and(BankAccount::factory()->create())->toBeInstanceOf(BankAccount::class)
        ->and(NumberSeries::factory()->create())->toBeInstanceOf(NumberSeries::class)
        ->and(Contact::factory()->create())->toBeInstanceOf(Contact::class)
        ->and(PriceItem::factory()->create())->toBeInstanceOf(PriceItem::class)
        ->and(Document::factory()->create())->toBeInstanceOf(Document::class)
        ->and(Payment::factory()->create())->toBeInstanceOf(Payment::class);
});

it('casts type and status to enums', function () {
    $document = Document::factory()->create();

    expect($document->fresh()->type)->toBe(DocumentType::Invoice)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Draft);
});

it('allows only one company profile', function () {
    CompanyProfile::factory()->create();

    expect(fn () => CompanyProfile::factory()->create())->toThrow(QueryException::class);
});

it('exposes the single company profile', function () {
    expect(CompanyProfile::current())->toBeNull();

    $profile = CompanyProfile::factory()->create();

    expect(CompanyProfile::current()->is($profile))->toBeTrue();
});

it('keeps exactly one default bank account', function () {
    $first = BankAccount::factory()->default()->create();
    $second = BankAccount::factory()->default()->create();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and(BankAccount::default()->is($second))->toBeTrue();
});

it('keeps exactly one default number series per document type', function () {
    $invoiceOld = NumberSeries::factory()->create();
    $invoiceNew = NumberSeries::factory()->create();
    $quote = NumberSeries::factory()->forType(DocumentType::Quote)->create();

    expect($invoiceOld->fresh()->is_default)->toBeFalse()
        ->and($invoiceNew->fresh()->is_default)->toBeTrue()
        ->and($quote->fresh()->is_default)->toBeTrue()
        ->and(NumberSeries::defaultFor(DocumentType::Invoice)->is($invoiceNew))->toBeTrue();
});

it('forbids two documents with the same number in one series', function () {
    $series = NumberSeries::factory()->create();

    Document::factory()->create(['series_id' => $series->id, 'number' => '2026001']);

    expect(fn () => Document::factory()->create(['series_id' => $series->id, 'number' => '2026001']))
        ->toThrow(QueryException::class);
});

it('allows many drafts without a number', function () {
    Document::factory()->count(3)->create();

    expect(Document::query()->whereNull('number')->count())->toBe(3);
});

it('relates documents to items, payments and contact', function () {
    $document = draftInvoice([[1, 100], [2, 50]]);

    expect($document->items)->toHaveCount(2)
        ->and($document->contact)->toBeInstanceOf(Contact::class)
        ->and($document->payments)->toHaveCount(0);
});

it('recalculates stored line totals and document totals', function () {
    $document = draftInvoice([[3, 100], ['1.5', '99.99']]);

    app(RecalculateDocument::class)($document, false);

    $document = $document->fresh();

    expect($document->items->pluck('line_total')->all())->toBe(['300.00', '149.99'])
        ->and($document->subtotal)->toBe('449.99')
        ->and($document->total)->toBe('449.99');
});
