<?php

use App\Actions\Documents\RecordPayment;
use App\Domain\Documents\ComputedStatus;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use Illuminate\Support\Carbon;

function onDay(string $date = '2026-04-01'): Carbon
{
    return Carbon::parse($date);
}

function issuedInvoice(string $due): Document
{
    return issueInvoice(draftInvoice(attributes: ['due_date' => $due]), '2026-03-01');
}

it('is overdue the day after the due date', function () {
    $invoice = issuedInvoice('2026-03-31');

    expect($invoice->computedStatus(onDay()))->toBe(ComputedStatus::Overdue)
        ->and($invoice->isOverdue(onDay()))->toBeTrue();
});

it('is not overdue on the due date itself', function () {
    expect(issuedInvoice('2026-04-01')->isOverdue(onDay()))->toBeFalse();
});

it('is not overdue before the due date', function () {
    expect(issuedInvoice('2026-04-15')->computedStatus(onDay()))->toBeNull();
});

it('is overdue when only partially paid', function () {
    $invoice = issuedInvoice('2026-03-31');
    app(RecordPayment::class)($invoice, '100.00');

    expect($invoice->fresh()->isOverdue(onDay()))->toBeTrue();
});

it('is not overdue when fully paid', function () {
    $invoice = issuedInvoice('2026-03-31');
    app(RecordPayment::class)($invoice, '1000.00');

    expect($invoice->fresh()->isOverdue(onDay()))->toBeFalse();
});

it('is never overdue for a draft', function () {
    $draft = draftInvoice(attributes: ['due_date' => '2026-01-01']);

    expect($draft->isOverdue(onDay()))->toBeFalse();
});

it('is never overdue when cancelled', function () {
    $invoice = issuedInvoice('2026-03-31');
    $invoice->update(['status' => DocumentStatus::Cancelled]);

    expect($invoice->isOverdue(onDay()))->toBeFalse();
});

it('uses the current date by default', function () {
    $invoice = issuedInvoice('2026-03-31');

    Carbon::setTestNow('2026-04-01 08:00:00');
    expect($invoice->isOverdue())->toBeTrue();

    Carbon::setTestNow('2026-03-31 23:00:00');
    expect($invoice->isOverdue())->toBeFalse();

    Carbon::setTestNow();
});

describe('quotes', function () {
    function issuedQuote(string $validUntil, ?DocumentStatus $status = null): Document
    {
        CompanyProfile::current() ?? CompanyProfile::factory()->create();
        NumberSeries::factory()->forType(DocumentType::Quote)->create();
        $quote = Document::factory()->quote()->create(['valid_until' => $validUntil]);
        DocumentItem::factory()->create(['document_id' => $quote->id]);
        $quote = issueInvoice($quote, '2026-03-01');

        if ($status !== null) {
            $quote->update(['status' => $status]);
        }

        return $quote;
    }

    it('expires the day after valid_until when created', function () {
        expect(issuedQuote('2026-03-31')->computedStatus(onDay()))->toBe(ComputedStatus::Expired);
    });

    it('expires when sent', function () {
        expect(issuedQuote('2026-03-31', DocumentStatus::Sent)->isExpired(onDay()))->toBeTrue();
    });

    it('does not expire on the last day of validity', function () {
        expect(issuedQuote('2026-04-01')->isExpired(onDay()))->toBeFalse();
    });

    it('does not expire once ordered, invoiced or rejected', function (DocumentStatus $status) {
        expect(issuedQuote('2026-03-31', $status)->isExpired(onDay()))->toBeFalse();
    })->with([DocumentStatus::Ordered, DocumentStatus::Invoiced, DocumentStatus::Rejected]);

    it('is never overdue', function () {
        expect(issuedQuote('2026-03-31')->isOverdue(onDay()))->toBeFalse();
    });

    it('is not expired as an invoice would be', function () {
        expect(issuedInvoice('2026-03-31')->isExpired(onDay()))->toBeFalse();
    });
});
