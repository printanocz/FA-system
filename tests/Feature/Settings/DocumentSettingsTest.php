<?php

use App\Actions\Documents\CreateDraftDocument;
use App\Actions\Settings\UpdateDocumentSettings;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Settings\DocumentSettings;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->settings = app(DocumentSettings::class);
    $this->update = app(UpdateDocumentSettings::class);
});

it('returns the built-in defaults when nothing is stored', function () {
    expect($this->settings->all())->toBe([
        'due_days' => 14,
        'quote_validity_days' => 14,
        'constant_symbol' => null,
        'round_total' => false,
        'text_before' => null,
        'text_after' => null,
    ]);
});

it('stores and reads values with their types', function () {
    ($this->update)([
        'due_days' => '30',
        'quote_validity_days' => 21,
        'constant_symbol' => '0308',
        'round_total' => true,
        'text_before' => 'Fakturuji Vám:',
        'text_after' => 'Děkuji.',
    ]);

    expect($this->settings->dueDays())->toBe(30)
        ->and($this->settings->quoteValidityDays())->toBe(21)
        ->and($this->settings->constantSymbol())->toBe('0308')
        ->and($this->settings->roundTotal())->toBeTrue()
        ->and($this->settings->textBefore())->toBe('Fakturuji Vám:')
        ->and($this->settings->textAfter())->toBe('Děkuji.');
});

it('changes only the keys that were sent', function () {
    ($this->update)(['due_days' => 30, 'constant_symbol' => '0308']);
    ($this->update)(['due_days' => 10]);

    expect($this->settings->dueDays())->toBe(10)->and($this->settings->constantSymbol())->toBe('0308');
});

it('turns blank text and symbol into null', function () {
    ($this->update)(['constant_symbol' => '0308', 'text_before' => 'x']);
    ($this->update)(['constant_symbol' => '  ', 'text_before' => '']);

    expect($this->settings->constantSymbol())->toBeNull()->and($this->settings->textBefore())->toBeNull();
});

it('rejects invalid values', function (array $input, string $field) {
    try {
        ($this->update)($input);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);

        return;
    }

    $this->fail('Validation did not fail.');
})->with([
    'negative due days' => [['due_days' => -1], 'due_days'],
    'huge due days' => [['due_days' => 366], 'due_days'],
    'text due days' => [['due_days' => 'abc'], 'due_days'],
    'zero validity' => [['quote_validity_days' => 0], 'quote_validity_days'],
    'letters in KS' => [['constant_symbol' => 'KS'], 'constant_symbol'],
    'long KS' => [['constant_symbol' => '12345678901'], 'constant_symbol'],
    'long text' => [['text_after' => str_repeat('a', 2001)], 'text_after'],
]);

it('stores nothing when validation fails', function () {
    try {
        ($this->update)(['due_days' => 30, 'constant_symbol' => 'KS']);
    } catch (ValidationException) {
    }

    expect($this->settings->dueDays())->toBe(14);
});

it('lets the contact due days win over the setting', function () {
    ($this->update)(['due_days' => 30]);

    expect($this->settings->dueDaysFor(null))->toBe(30)
        ->and($this->settings->dueDaysFor(Contact::factory()->make(['default_due_days' => null])))->toBe(30)
        ->and($this->settings->dueDaysFor(Contact::factory()->make(['default_due_days' => 7])))->toBe(7)
        ->and($this->settings->dueDaysFor(Contact::factory()->make(['default_due_days' => 0])))->toBe(0);
});

describe('new drafts', function () {
    beforeEach(function () {
        Carbon::setTestNow('2026-10-26 10:00:00');
        ($this->update)([
            'due_days' => 30,
            'quote_validity_days' => 21,
            'constant_symbol' => '0308',
            'round_total' => true,
            'text_before' => 'Fakturuji Vám za dodané služby:',
            'text_after' => 'Úrok z prodlení.',
        ]);
        $this->create = app(CreateDraftDocument::class);
    });

    afterEach(fn () => Carbon::setTestNow());

    it('take their defaults from the settings', function () {
        $draft = ($this->create)(DocumentType::Invoice, Contact::factory()->create());

        expect($draft->status)->toBe(DocumentStatus::Draft)
            ->and($draft->number)->toBeNull()
            ->and($draft->due_date->toDateString())->toBe('2026-11-25')
            ->and($draft->issue_date->toDateString())->toBe('2026-10-26')
            ->and($draft->ks)->toBe('0308')
            ->and($draft->round_total)->toBeTrue()
            ->and($draft->text_before)->toBe('Fakturuji Vám za dodané služby:')
            ->and($draft->text_after)->toBe('Úrok z prodlení.')
            ->and($draft->currency)->toBe('CZK');
    });

    it('prefer the due days of the contact', function () {
        $draft = ($this->create)(DocumentType::Invoice, Contact::factory()->create(['default_due_days' => 7]));

        expect($draft->due_date->toDateString())->toBe('2026-11-02');
    });

    it('can be overridden per document', function () {
        $draft = ($this->create)(DocumentType::Invoice, null, ['round_total' => false, 'ks' => null, 'due_date' => '2026-12-31']);

        expect($draft->round_total)->toBeFalse()
            ->and($draft->ks)->toBeNull()
            ->and($draft->due_date->toDateString())->toBe('2026-12-31');
    });

    it('use the quote validity and no payment fields for a quote', function () {
        $draft = ($this->create)(DocumentType::Quote, Contact::factory()->create());

        expect($draft->valid_until->toDateString())->toBe('2026-11-16')
            ->and($draft->due_date)->toBeNull()
            ->and($draft->ks)->toBeNull()
            ->and($draft->text_after)->toBeNull();
    });

    it('take the discount of the contact', function () {
        $draft = ($this->create)(DocumentType::Invoice, Contact::factory()->create(['default_discount_percent' => '5.00']));

        expect($draft->discount_percent)->toBe('5.00');
    });

    it('default to no rounding when the setting was never saved', function () {
        Setting::query()->delete();

        expect(($this->create)(DocumentType::Invoice)->round_total)->toBeFalse();
    });
});

describe('issuing', function () {
    it('uses the due days from the settings', function () {
        ($this->update)(['due_days' => 30]);
        NumberSeries::factory()->create();

        $issued = issueInvoice(draftInvoice(), '2026-03-10');

        expect($issued->due_date->toDateString())->toBe('2026-04-09');
    });

    it('prefers the contact due days over the settings', function () {
        ($this->update)(['due_days' => 30]);
        $draft = draftInvoice(attributes: ['contact_id' => Contact::factory()->create(['default_due_days' => 5])->id]);

        expect(issueInvoice($draft, '2026-03-10')->due_date->toDateString())->toBe('2026-03-15');
    });

    it('uses the quote validity from the settings', function () {
        ($this->update)(['quote_validity_days' => 45]);
        CompanyProfile::factory()->create();
        NumberSeries::factory()->forType(DocumentType::Quote)->create();
        $quote = Document::factory()->quote()->create();
        DocumentItem::factory()->create(['document_id' => $quote->id]);

        expect(issueInvoice($quote, '2026-03-10')->valid_until->toDateString())->toBe('2026-04-24');
    });

    it('keeps 14 days when the setting does not exist', function () {
        expect(issueInvoice(draftInvoice(), '2026-03-10')->due_date->toDateString())->toBe('2026-03-24');
    });
});
