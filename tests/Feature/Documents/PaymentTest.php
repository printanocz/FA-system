<?php

use App\Actions\Documents\DeletePayment;
use App\Actions\Documents\RecordPayment;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Domain\Documents\Exceptions\OverpaymentException;
use App\Domain\Documents\PaymentMethod;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;

function pay(Document $document, string|int $amount, ?string $date = null)
{
    return app(RecordPayment::class)($document, $amount, $date);
}

beforeEach(fn () => $this->invoice = issueInvoice(draftInvoice([[1, 1000]]), '2026-03-10'));

it('marks a fully paid invoice as paid and stores the payment date', function () {
    pay($this->invoice, '1000.00', '2026-03-20');

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe(DocumentStatus::Paid)
        ->and($invoice->paid_total)->toBe('1000.00')
        ->and($invoice->paid_at->toDateString())->toBe('2026-03-20')
        ->and($invoice->remainingAmount()->__toString())->toBe('0.00');
});

it('handles a partial payment', function () {
    pay($this->invoice, '400.00', '2026-03-15');

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe(DocumentStatus::PartiallyPaid)
        ->and($invoice->paid_total)->toBe('400.00')
        ->and($invoice->paid_at)->toBeNull()
        ->and($invoice->remainingAmount()->__toString())->toBe('600.00');
});

it('completes a partially paid invoice with a second payment', function () {
    pay($this->invoice, '400.00', '2026-03-15');
    pay($this->invoice, '600.00', '2026-03-25');

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe(DocumentStatus::Paid)
        ->and($invoice->paid_total)->toBe('1000.00')
        ->and($invoice->paid_at->toDateString())->toBe('2026-03-25')
        ->and($invoice->payments()->count())->toBe(2);
});

it('rejects an overpayment', function () {
    pay($this->invoice, '1000.01');
})->throws(OverpaymentException::class);

it('rejects a payment that exceeds the remaining amount', function () {
    pay($this->invoice, '700.00');
    pay($this->invoice, '300.01');
})->throws(OverpaymentException::class);

it('does not store anything for a rejected payment', function () {
    try {
        pay($this->invoice, '5000.00');
    } catch (OverpaymentException) {
    }

    $invoice = $this->invoice->fresh();

    expect($invoice->payments()->count())->toBe(0)
        ->and($invoice->paid_total)->toBe('0.00')
        ->and($invoice->status)->toBe(DocumentStatus::Issued);
});

it('rejects non-positive and malformed amounts', function (string|int $amount) {
    pay($this->invoice, $amount);
})->with(['0', '-5', 'abc', '10.005'])->throws(InvalidArgumentException::class);

it('rejects a payment on a draft', function () {
    pay(draftInvoice(), '100.00');
})->throws(InvalidDocumentStateException::class);

it('rejects a payment on a paid invoice', function () {
    pay($this->invoice, '1000.00');
    pay($this->invoice, '1.00');
})->throws(InvalidDocumentStateException::class);

it('rejects a payment on a quote', function () {
    CompanyProfile::current();
    NumberSeries::factory()->forType(DocumentType::Quote)->create();
    $quote = Document::factory()->quote()->create();
    DocumentItem::factory()->create(['document_id' => $quote->id]);

    pay(issueInvoice($quote), '1.00');
})->throws(InvalidDocumentStateException::class);

it('stores method and note of a payment', function () {
    $payment = app(RecordPayment::class)($this->invoice, '100.00', '2026-03-12', PaymentMethod::Cash, 'Hotově');

    expect($payment->method)->toBe(PaymentMethod::Cash)
        ->and($payment->note)->toBe('Hotově')
        ->and($payment->amount)->toBe('100.00');
});

it('accepts a payment of an odd amount of pennies', function () {
    $invoice = issueInvoice(draftInvoice([['1.5', '99.99']]), '2026-03-10');

    pay($invoice, '149.99');

    expect($invoice->fresh()->status)->toBe(DocumentStatus::Paid);
});

describe('deleting a payment', function () {
    it('recalculates a fully paid invoice back to partially paid', function () {
        $first = pay($this->invoice, '400.00', '2026-03-12');
        $last = pay($this->invoice, '600.00', '2026-03-20');

        app(DeletePayment::class)($last);

        $invoice = $this->invoice->fresh();

        expect($invoice->status)->toBe(DocumentStatus::PartiallyPaid)
            ->and($invoice->paid_total)->toBe('400.00')
            ->and($invoice->paid_at)->toBeNull()
            ->and($invoice->payments()->pluck('id')->all())->toBe([$first->id]);
    });

    it('returns an unsent invoice to issued after its only payment is deleted', function () {
        $payment = pay($this->invoice, '1000.00', '2026-03-12');

        app(DeletePayment::class)($payment);

        $invoice = $this->invoice->fresh();

        expect($invoice->status)->toBe(DocumentStatus::Issued)
            ->and($invoice->paid_total)->toBe('0.00')
            ->and($invoice->paid_at)->toBeNull();
    });

    it('returns a sent invoice to sent', function () {
        $this->invoice->forceFill(['sent_at' => now(), 'status' => DocumentStatus::Sent])->save();
        $payment = pay($this->invoice, '300.00', '2026-03-12');

        app(DeletePayment::class)($payment);

        expect($this->invoice->fresh()->status)->toBe(DocumentStatus::Sent);
    });

    it('allows paying again after a deletion', function () {
        $payment = pay($this->invoice, '1000.00', '2026-03-12');
        app(DeletePayment::class)($payment);

        pay($this->invoice->fresh(), '1000.00', '2026-03-25');

        expect($this->invoice->fresh()->status)->toBe(DocumentStatus::Paid)
            ->and($this->invoice->fresh()->paid_at->toDateString())->toBe('2026-03-25');
    });

    it('orders payments by date and id, so the last one is the newest date', function () {
        $newerDate = pay($this->invoice, '300.00', '2026-03-20');
        $olderDate = pay($this->invoice, '200.00', '2026-03-12');

        expect(fn () => app(DeletePayment::class)($olderDate))->toThrow(InvalidDocumentStateException::class);

        app(DeletePayment::class)($newerDate);

        expect($this->invoice->fresh()->paid_total)->toBe('200.00');
    });

    it('refuses to delete a payment that is not the last one', function () {
        $first = pay($this->invoice, '400.00', '2026-03-12');
        pay($this->invoice, '100.00', '2026-03-15');

        app(DeletePayment::class)($first);
    })->throws(InvalidDocumentStateException::class, 'Smazat jde jen poslední úhrada');

    it('refuses to touch the payments of a cancelled invoice', function () {
        $payment = pay($this->invoice, '400.00', '2026-03-12');
        $this->invoice->forceFill(['status' => DocumentStatus::Cancelled])->save();

        app(DeletePayment::class)($payment);
    })->throws(InvalidDocumentStateException::class);
});
