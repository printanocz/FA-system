<?php

use App\Domain\Documents\DocumentType;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;

it('keeps the length of the due term when issued later than the draft date', function () {
    $draft = draftInvoice(attributes: ['issue_date' => '2026-10-20', 'due_date' => '2026-11-03']);

    expect(issueInvoice($draft, '2026-10-25')->due_date->toDateString())->toBe('2026-11-08');
});

it('keeps the length of the term when issued earlier than the draft date', function () {
    $draft = draftInvoice(attributes: ['issue_date' => '2026-10-20', 'due_date' => '2026-11-03']);

    expect(issueInvoice($draft, '2026-10-18')->due_date->toDateString())->toBe('2026-11-01');
});

it('keeps the due date when issued on the draft date', function () {
    $draft = draftInvoice(attributes: ['issue_date' => '2026-10-20', 'due_date' => '2026-11-03']);

    expect(issueInvoice($draft, '2026-10-20')->due_date->toDateString())->toBe('2026-11-03');
});

it('keeps a due date given without a draft date', function () {
    $draft = draftInvoice(attributes: ['due_date' => '2026-12-31']);

    expect(issueInvoice($draft, '2026-10-25')->due_date->toDateString())->toBe('2026-12-31');
});

it('falls back to the default term when the draft has no due date', function () {
    $draft = draftInvoice(attributes: ['issue_date' => '2026-10-20']);

    expect(issueInvoice($draft, '2026-10-25')->due_date->toDateString())->toBe('2026-11-08');
});

it('keeps the length of the validity of a quote', function () {
    CompanyProfile::factory()->create();
    NumberSeries::factory()->forType(DocumentType::Quote)->create();
    $quote = Document::factory()->quote()->create(['issue_date' => '2026-10-20', 'valid_until' => '2026-11-10']);
    DocumentItem::factory()->create(['document_id' => $quote->id]);

    expect(issueInvoice($quote, '2026-10-25')->valid_until->toDateString())->toBe('2026-11-15');
});

it('issues a draft with the draft date as the issue date when none is given', function () {
    $draft = draftInvoice(attributes: ['issue_date' => '2026-10-20', 'due_date' => '2026-11-03']);

    $issued = issueInvoice($draft);

    expect($issued->issue_date->toDateString())->toBe('2026-10-20')->and($issued->due_date->toDateString())->toBe('2026-11-03');
});
