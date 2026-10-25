<?php

use App\Actions\Documents\RecordPayment;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\Exceptions\DocumentLockedException;
use App\Domain\Documents\Exceptions\DocumentNotDeletableException;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;

it('lets a draft change freely', function () {
    $draft = draftInvoice();

    $draft->update(['total' => '999.00', 'discount_percent' => '10.00']);
    $draft->items()->first()->update(['unit_price' => '5.00']);

    expect($draft->fresh()->total)->toBe('999.00');
});

it('rejects changes of financial fields on a locked document', function (string $field, mixed $value) {
    $issued = issueInvoice(draftInvoice(), '2026-03-10');

    $issued->{$field} = $value;
    $issued->save();
})->with([
    'total' => ['total', '1.00'],
    'subtotal' => ['subtotal', '1.00'],
    'vat_total' => ['vat_total', '5.00'],
    'discount' => ['discount_percent', '50.00'],
    'rounding' => ['rounding', '0.40'],
    'number' => ['number', '9999999'],
    'variable symbol' => ['vs', '1'],
    'currency' => ['currency', 'EUR'],
    'issue date' => ['issue_date', '2020-01-01'],
    'buyer snapshot' => ['buyer_snapshot', ['name' => 'Podvrh']],
    'supplier snapshot' => ['supplier_snapshot', ['name' => 'Podvrh']],
    'unlocking' => ['locked_at', null],
])->throws(DocumentLockedException::class);

it('still allows non-financial fields on a locked document', function () {
    $issued = issueInvoice(draftInvoice(), '2026-03-10');

    $issued->update(['note_internal' => 'Poznámka jen pro mě', 'sent_at' => now()]);

    expect($issued->fresh()->note_internal)->toBe('Poznámka jen pro mě');
});

it('rejects adding, changing and removing items of a locked document', function () {
    $issued = issueInvoice(draftInvoice(), '2026-03-10');
    $item = $issued->items()->first();

    expect(fn () => DocumentItem::factory()->create(['document_id' => $issued->id]))
        ->toThrow(DocumentLockedException::class)
        ->and(fn () => $item->update(['qty' => '2.0000']))
        ->toThrow(DocumentLockedException::class)
        ->and(fn () => $item->delete())
        ->toThrow(DocumentLockedException::class);
});

describe('deleting', function () {
    it('deletes a draft', function () {
        $draft = draftInvoice();

        $draft->delete();

        expect(Document::query()->count())->toBe(0);
    });

    it('deletes the last document in the series and gives its number back', function () {
        $issued = issueInvoice(draftInvoice(), '2026-03-10');

        $issued->delete();

        expect(Document::query()->count())->toBe(0)
            ->and(NumberSeries::query()->first()->next_number)->toBe(1)
            ->and(issueInvoice(draftInvoice(), '2026-03-10')->number)->toBe('2026001');
    });

    it('refuses to delete a document that is not the last in the series', function () {
        $first = issueInvoice(draftInvoice(), '2026-03-10');
        issueInvoice(draftInvoice(), '2026-03-11');

        $first->delete();
    })->throws(DocumentNotDeletableException::class);

    it('refuses to delete a sent document', function () {
        $issued = issueInvoice(draftInvoice(), '2026-03-10');
        $issued->update(['status' => DocumentStatus::Sent, 'sent_at' => now()]);

        $issued->delete();
    })->throws(DocumentNotDeletableException::class);

    it('refuses to delete a paid document', function () {
        $issued = issueInvoice(draftInvoice(), '2026-03-10');
        app(RecordPayment::class)($issued, '1000.00');

        $issued->fresh()->delete();
    })->throws(DocumentNotDeletableException::class);

    it('refuses to delete a cancelled document', function () {
        $issued = issueInvoice(draftInvoice(), '2026-03-10');
        $issued->update(['status' => DocumentStatus::Cancelled]);

        $issued->delete();
    })->throws(DocumentNotDeletableException::class);

    it('keeps the counter when deleting a draft', function () {
        issueInvoice(draftInvoice(), '2026-03-10');
        draftInvoice()->delete();

        expect(NumberSeries::query()->first()->next_number)->toBe(2);
    });
});
