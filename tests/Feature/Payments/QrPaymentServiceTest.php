<?php

use App\Actions\Documents\RecordPayment;
use App\Domain\Documents\DocumentType;
use App\Domain\Payments\Exceptions\InvalidPaymentDataException;
use App\Domain\Payments\QrPaymentService;
use App\Models\BankAccount;
use App\Models\Document;
use App\Models\NumberSeries;

beforeEach(function () {
    $this->qr = app(QrPaymentService::class);
    BankAccount::factory()->default()->create(['account_number' => '2401234567', 'bank_code' => '0800', 'iban' => null, 'swift' => null, 'currency' => 'CZK']);
});

function qrInvoice(array $items = [[38, 400]])
{
    return issueInvoice(draftInvoice($items, ['ks' => '0308']), '2026-10-25');
}

it('builds the payload from the invoice', function () {
    $invoice = qrInvoice();

    expect($this->qr->payload($invoice))->toBe(
        'SPD*1.0*ACC:CZ1508000000002401234567*AM:15200.00*CC:CZK*X-VS:2026001*X-KS:0308*DT:20261108*MSG:FAKTURA 2026001'
    );
});

it('asks only for the remaining amount after a partial payment', function () {
    $invoice = qrInvoice();
    app(RecordPayment::class)($invoice, '5200.00');

    expect($this->qr->payload($invoice->fresh()))->toContain('*AM:10000.00*');
});

it('prefers an IBAN stored on the account', function () {
    BankAccount::query()->delete();
    BankAccount::factory()->default()->create(['account_number' => '2401234567', 'bank_code' => '0800', 'iban' => 'CZ65 0800 0000 1920 0014 5399', 'swift' => 'GIBACZPX']);

    expect($this->qr->payload(qrInvoice()))->toContain('ACC:CZ6508000000192000145399+GIBACZPX*');
});

it('renders an SVG', function () {
    $svg = $this->qr->forDocument(qrInvoice());

    expect($svg)->toContain('<svg')->and($svg)->toContain('</svg>');
});

it('refuses a paid invoice', function () {
    $invoice = qrInvoice();
    app(RecordPayment::class)($invoice, '15200.00');

    $this->qr->forDocument($invoice->fresh());
})->throws(InvalidPaymentDataException::class);

it('refuses a quote', function () {
    $quote = Document::factory()->quote()->create();

    $this->qr->payload($quote);
})->throws(InvalidPaymentDataException::class);

it('refuses a document without a bank account', function () {
    $draft = draftInvoice();
    $draft->update(['bank_account_snapshot' => null, 'bank_account_id' => null, 'total' => '100.00']);
    BankAccount::query()->delete();

    $this->qr->payload($draft->fresh());
})->throws(InvalidPaymentDataException::class);

it('writes ZALOHA into the message of a proforma invoice', function () {
    NumberSeries::factory()->forType(DocumentType::Proforma)->create();
    $proforma = issueInvoice(draftInvoice([[1, 500]], ['type' => DocumentType::Proforma]), '2026-10-25');

    expect($this->qr->payload($proforma))->toEndWith('*MSG:ZALOHA 2026001')
        ->and($this->qr->payload(qrInvoice()))->toEndWith('*MSG:FAKTURA 2026001');
});
