<?php

use App\Domain\Documents\DocumentType;
use App\Domain\Documents\PaymentMethod;
use App\Domain\Pdf\InvoicePdf;
use App\Domain\Pdf\PdfFonts;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Počet stran v binárním PDF. */
function pdfPages(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page(?![a-z])#', $pdf);
}

/** Vystavená faktura s platným účtem (IBAN z továrny není platný, QR by nešel sestavit). */
function pdfInvoice(array $items = [[1, 14500], [10, 600]], array $attributes = []): Document
{
    BankAccount::default() ?? BankAccount::factory()->default()->create(['iban' => 'CZ6508000000192000145399']);
    $contact = Contact::factory()->create(['name' => 'Pekárna Hrubý s.r.o.', 'ico' => '08765432']);

    return issueInvoice(
        draftInvoice($items, array_merge(['contact_id' => $contact->id, 'issue_date' => '2026-10-12', 'due_date' => '2026-10-26'], $attributes)),
        '2026-10-12',
    );
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-25 09:00:00');
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

describe('HTML template', function () {
    it('contains the invoice data from the snapshots', function () {
        $invoice = pdfInvoice();
        $html = app(InvoicePdf::class)->html($invoice);

        expect($html)
            ->toContain($invoice->number)
            ->toContain('Pekárna Hrubý s.r.o.')
            ->toContain('08765432')
            ->toContain($invoice->vs)
            ->toContain('CZ6508000000192000145399')
            ->toContain('Nejsem plátce DPH.')
            ->toContain('Fyzická osoba zapsaná v živnostenském rejstříku')
            ->toContain('Strana {PAGENO}/{nbpg}')
            ->toContain("20\u{00A0}500,00")
            ->and(mb_stripos($html, 'daňový doklad'))->toBeFalse();
    });

    it('keeps the buyer and the supplier from the snapshot after the contact changes', function () {
        $invoice = pdfInvoice();
        $invoice->contact->update(['name' => 'Úplně jiný odběratel a.s.', 'street' => 'Nová 99']);
        CompanyProfile::current()->update(['name' => 'Přejmenovaná firma']);

        $html = app(InvoicePdf::class)->html($invoice->fresh());

        expect($html)
            ->toContain('Pekárna Hrubý s.r.o.')
            ->toContain('Adam Fujak')
            ->not->toContain('Úplně jiný odběratel')
            ->not->toContain('Nová 99')
            ->not->toContain('Přejmenovaná firma');
    });

    it('shows the taxable date only when the switch is on', function () {
        $off = pdfInvoice(attributes: ['show_taxable_date' => false]);
        $on = pdfInvoice(attributes: ['show_taxable_date' => true]);

        expect(app(InvoicePdf::class)->html($off))->not->toContain('Datum zdan. plnění')
            ->and(app(InvoicePdf::class)->html($on))->toContain('Datum zdan. plnění');
    });

    it('draws a QR payment for the remaining amount and leaves it out once the invoice is paid', function () {
        $invoice = pdfInvoice();
        expect(app(InvoicePdf::class)->html($invoice))->toContain('data:image/svg+xml;base64,');

        $invoice->forceFill(['paid_total' => $invoice->total])->save();

        expect(app(InvoicePdf::class)->html($invoice->fresh()))->not->toContain('data:image/svg+xml');
    });

    it('leaves out the bank block for a cash invoice', function () {
        $invoice = pdfInvoice(attributes: ['payment_method' => PaymentMethod::Cash]);
        $html = app(InvoicePdf::class)->html($invoice);

        expect($html)->not->toContain('CZ6508000000192000145399')
            ->not->toContain('data:image/svg+xml')
            ->toContain('Hotově');
    });
});

describe('PDF output', function () {
    it('renders a PDF', function () {
        $pdf = app(InvoicePdf::class)->render(pdfInvoice());

        expect($pdf)->toStartWith('%PDF')->and(pdfPages($pdf))->toBe(1);
    });

    it('puts a watermark on a draft but not on an issued invoice', function () {
        $watermark = '/ca '.InvoicePdf::WATERMARK_ALPHA;

        expect(app(InvoicePdf::class)->render(draftInvoice()))->toContain($watermark)
            ->and(app(InvoicePdf::class)->render(pdfInvoice()))->not->toContain($watermark);
    });

    it('renders a draft without storing anything', function () {
        $draft = draftInvoice([[1, 1000]]);

        $pdf = app(InvoicePdf::class)->contents($draft);

        expect($pdf)->toStartWith('%PDF')
            ->and(Storage::disk('local')->allFiles())->toBe([])
            ->and($draft->fresh()->pdf_path)->toBeNull();
    });

    it('breaks 60 items into more pages without an error', function () {
        $pdf = app(InvoicePdf::class)->render(pdfInvoice(array_fill(0, 60, [1, 1000])));

        expect($pdf)->toStartWith('%PDF')->and(pdfPages($pdf))->toBeGreaterThan(1);
    });
});

describe('archive', function () {
    it('stores the PDF when the invoice is issued', function () {
        $invoice = pdfInvoice();
        $path = "documents/2026/{$invoice->number}.pdf";

        expect($invoice->fresh()->pdf_path)->toBe($path);
        Storage::disk('local')->assertExists($path);
        expect(Storage::disk('local')->get($path))->toStartWith('%PDF');
    });

    it('never overwrites an existing archive file', function () {
        $invoice = pdfInvoice();
        $path = $invoice->fresh()->pdf_path;
        Storage::disk('local')->put($path, '%PDF-original');

        app(InvoicePdf::class)->archive($invoice->fresh());
        $this->get("/faktury/{$invoice->id}/pdf")->assertOk();

        expect(Storage::disk('local')->get($path))->toBe('%PDF-original');
    });

    it('does not archive a quote', function () {
        CompanyProfile::factory()->create();
        NumberSeries::factory()->forType(DocumentType::Quote)->create();
        $quote = Document::factory()->quote()->create();
        DocumentItem::factory()->create(['document_id' => $quote->id]);

        issueInvoice($quote, '2026-10-12');

        expect($quote->fresh()->pdf_path)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('still issues the invoice when generating the archive fails and creates the file on the first download', function () {
        $this->mock(InvoicePdf::class, fn ($mock) => $mock->shouldReceive('archive')->andThrow(new RuntimeException('boom')));
        Log::spy();

        $invoice = pdfInvoice();

        expect($invoice->fresh()->isLocked())->toBeTrue()
            ->and($invoice->fresh()->pdf_path)->toBeNull();
        Log::shouldHaveReceived('error')->once();

        app()->forgetInstance(InvoicePdf::class);
        $this->get("/faktury/{$invoice->id}/pdf")->assertOk();

        expect($invoice->fresh()->pdf_path)->toBe("documents/2026/{$invoice->number}.pdf");
        Storage::disk('local')->assertExists($invoice->fresh()->pdf_path);
    });
});

describe('route', function () {
    it('shows the archive inline and under the right name', function () {
        $invoice = pdfInvoice();

        $response = $this->get("/faktury/{$invoice->id}/pdf");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->headers->get('Content-Disposition'))
            ->toStartWith('inline')
            ->toContain("Faktura-{$invoice->number}.pdf")
            ->and($response->getContent())->toStartWith('%PDF');
    });

    it('downloads it as an attachment with ?stahnout=1', function () {
        $invoice = pdfInvoice();

        $response = $this->get("/faktury/{$invoice->id}/pdf?stahnout=1");

        expect($response->headers->get('Content-Disposition'))
            ->toStartWith('attachment')
            ->toContain("Faktura-{$invoice->number}.pdf");
    });

    it('creates the archive again when its file has gone missing', function () {
        $invoice = pdfInvoice();
        Storage::disk('local')->delete($invoice->fresh()->pdf_path);

        $this->get("/faktury/{$invoice->id}/pdf")->assertOk();

        Storage::disk('local')->assertExists($invoice->fresh()->pdf_path);
    });

    it('generates a draft on the fly and stores nothing', function () {
        $draft = draftInvoice();

        $response = $this->get("/faktury/{$draft->id}/pdf")->assertOk();

        expect($response->getContent())->toStartWith('%PDF')
            ->and($response->headers->get('Content-Disposition'))->toContain('Faktura-koncept.pdf')
            ->and(Storage::disk('local')->allFiles())->toBe([])
            ->and($draft->fresh()->pdf_path)->toBeNull();
    });

    it('answers 404 for a quote, a proforma and a credit note', function () {
        foreach ([DocumentType::Quote, DocumentType::Proforma, DocumentType::CreditNote] as $type) {
            $document = Document::factory()->create(['type' => $type]);

            $this->get("/faktury/{$document->id}/pdf")->assertNotFound();
        }
    });

    it('needs a signed-in user', function () {
        $invoice = pdfInvoice();
        auth()->forgetGuards();

        $this->get("/faktury/{$invoice->id}/pdf")->assertRedirect();
    });
});

describe('fonts', function () {
    it('falls back to dejavusans and warns only once', function () {
        Log::spy();
        $empty = sys_get_temp_dir().'/splatno-fonts-'.uniqid();
        mkdir($empty);

        $first = (new PdfFonts($empty))->resolve();
        (new PdfFonts($empty))->resolve();

        expect($first['families'])->toBe(['body' => 'dejavusans', 'mono' => 'dejavusansmono', 'display' => 'dejavusans']);
        Log::shouldHaveReceived('warning')->once();

        rmdir($empty);
    });

    it('registers the brand fonts that are present', function () {
        $dir = sys_get_temp_dir().'/splatno-fonts-'.uniqid();
        mkdir($dir);
        foreach (['DMSans-Regular.ttf', 'DMMono-Regular.ttf', 'CabinetGrotesk-Bold.ttf'] as $file) {
            touch($dir.'/'.$file);
        }

        $resolved = (new PdfFonts($dir))->resolve();

        expect($resolved['families'])->toBe(['body' => 'dmsans', 'mono' => 'dmmono', 'display' => 'cabinetgrotesk'])
            ->and($resolved['config']['fontdata']['dmsans']['B'])->toBe('DMSans-Regular.ttf')
            ->and($resolved['config']['default_font'])->toBe('dmsans');

        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    });
});

describe('app:sample-pdf', function () {
    it('writes a sample invoice', function () {
        $path = sys_get_temp_dir().'/splatno-sample-'.uniqid().'.pdf';

        $this->artisan('app:sample-pdf', ['path' => $path])->assertSuccessful();

        expect(file_get_contents($path))->toStartWith('%PDF');
        unlink($path);
    });
});
