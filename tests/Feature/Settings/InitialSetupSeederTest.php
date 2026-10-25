<?php

use App\Actions\Settings\UpdateDocumentSettings;
use App\Domain\Documents\DocumentType;
use App\Domain\Settings\DocumentSettings;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use Database\Seeders\InitialSetupSeeder;

function seedInitialSetup(): void
{
    test()->seed(InitialSetupSeeder::class);
}

it('creates the invoice and quote series', function () {
    seedInitialSetup();

    $invoice = NumberSeries::defaultFor(DocumentType::Invoice);
    $quote = NumberSeries::defaultFor(DocumentType::Quote);

    expect(NumberSeries::query()->count())->toBe(2)
        ->and($invoice->prefix)->toBe('')
        ->and($invoice->format)->toBe('{YYYY}{NNN}')
        ->and($quote->prefix)->toBe('N')
        ->and($quote->format)->toBe('{YYYY}{NNN}')
        ->and($invoice->reset_yearly)->toBeTrue();
});

it('sets the default document values', function () {
    seedInitialSetup();

    $settings = app(DocumentSettings::class);

    expect($settings->dueDays())->toBe(14)
        ->and($settings->quoteValidityDays())->toBe(14)
        ->and($settings->constantSymbol())->toBe('0308')
        ->and($settings->roundTotal())->toBeFalse()
        ->and($settings->textBefore())->toBe('Fakturuji Vám za dodané služby:')
        ->and($settings->textAfter())->toContain('úrok z prodlení');
});

it('can run twice without duplicating anything', function () {
    seedInitialSetup();
    seedInitialSetup();

    expect(NumberSeries::query()->count())->toBe(2);
});

it('does not overwrite settings and counters that were changed', function () {
    seedInitialSetup();
    app(UpdateDocumentSettings::class)(['due_days' => 30, 'text_before' => 'Jiný text']);
    NumberSeries::defaultFor(DocumentType::Invoice)->update(['next_number' => 61]);

    seedInitialSetup();

    expect(app(DocumentSettings::class)->dueDays())->toBe(30)
        ->and(app(DocumentSettings::class)->textBefore())->toBe('Jiný text')
        ->and(NumberSeries::defaultFor(DocumentType::Invoice)->next_number)->toBe(61);
});

it('lets the first invoice and quote be issued with the seeded series', function () {
    seedInitialSetup();
    CompanyProfile::factory()->create();
    BankAccount::factory()->default()->create();

    $invoice = issueInvoice(draftInvoice(), '2026-03-10');

    $quote = Document::factory()->quote()->create();
    DocumentItem::factory()->create(['document_id' => $quote->id]);

    expect($invoice->number)->toBe('2026001')
        ->and(issueInvoice($quote, '2026-03-10')->number)->toBe('N2026001');
});
