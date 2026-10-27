<?php

use App\Domain\Documents\CalculatorLine;
use App\Domain\Documents\DocumentCalculator;
use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use App\Models\PriceItem;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** Hodnoty formuláře tak, jak je posílá editor (data ISO, položky jen s vyplněnými poli). */
function invoicePayload(?Contact $contact = null, array $overrides = []): array
{
    return array_merge([
        'contact_id' => ($contact ?? Contact::factory()->create())->id,
        'issue_date' => '2026-10-25',
        'due_date' => '2026-11-08',
        'taxable_date' => null,
        'order_number' => 'OBJ-7',
        'payment_method' => 'bank_transfer',
        'bank_account_id' => null,
        'text_before' => 'Fakturuji Vám:',
        'text_after' => 'Děkuji.',
        'note_internal' => 'Interní',
        'discount_percent' => '0',
        'round_total' => false,
        'items' => [
            ['name' => 'Správa webu', 'description' => '', 'qty' => '38', 'unit' => 'hod.', 'unit_price' => '400,00', 'price_item_id' => null],
        ],
    ], $overrides);
}

function prepareIssuing(): void
{
    CompanyProfile::current() ?? CompanyProfile::factory()->create();
    BankAccount::default() ?? BankAccount::factory()->default()->create();
    NumberSeries::defaultFor(DocumentType::Invoice) ?? NumberSeries::factory()->create();
}

beforeEach(function () {
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

describe('access', function () {
    it('keeps the editor behind login', function () {
        auth()->logout();

        $this->get('/faktury/nova')->assertRedirect('/login');
        $this->post('/faktury/prepocet', [])->assertRedirect('/login');
        $this->getJson('/adresar/hledat')->assertUnauthorized();
    });
});

describe('new invoice', function () {
    it('opens an empty editor without creating anything', function () {
        $this->get('/faktury/nova')->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Editor')
            ->where('document', null)
            ->where('contact', null)
            ->where('form.issue_date', now()->toDateString())
            ->where('form.due_date', now()->addDays(14)->toDateString())
            ->where('form.payment_method', 'bank_transfer')
            ->has('form.items', 0));

        expect(Document::count())->toBe(0);
    });

    it('prefills the customer, due date and discount from the contact', function () {
        $contact = Contact::factory()->create(['default_due_days' => 30, 'default_discount_percent' => '5.00']);

        $this->get("/faktury/nova?kontakt={$contact->id}")->assertInertia(fn (Assert $page) => $page
            ->where('contact.id', $contact->id)
            ->where('form.contact_id', $contact->id)
            ->where('form.due_date', now()->addDays(30)->toDateString())
            ->where('form.discount_percent', '5.00'));
    });

    it('does not prefill an archived contact', function () {
        $contact = Contact::factory()->create(['archived_at' => now()]);

        $this->get("/faktury/nova?kontakt={$contact->id}")->assertInertia(fn (Assert $page) => $page
            ->where('contact', null));
    });

    it('offers bank accounts and the defaults from settings', function () {
        BankAccount::factory()->default()->create(['name' => 'Hlavní']);
        BankAccount::factory()->create(['name' => 'Druhý']);

        $this->get('/faktury/nova')->assertInertia(fn (Assert $page) => $page
            ->has('bankAccounts', 2)
            ->where('bankAccounts.0.name', 'Hlavní')
            ->where('bankAccounts.0.is_default', true)
            ->where('defaults.vat_payer', false));
    });
});

describe('saving a draft', function () {
    it('saves a draft with items and totals, without a number', function () {
        $contact = Contact::factory()->create();

        $response = $this->post('/faktury', invoicePayload($contact));

        $document = Document::firstOrFail();
        $response->assertRedirect("/faktury/{$document->id}/upravit");
        expect($document->status)->toBe(DocumentStatus::Draft)
            ->and($document->number)->toBeNull()
            ->and($document->contact_id)->toBe($contact->id)
            ->and($document->order_number)->toBe('OBJ-7')
            ->and($document->issue_date->toDateString())->toBe('2026-10-25')
            ->and($document->due_date->toDateString())->toBe('2026-11-08')
            ->and($document->items)->toHaveCount(1)
            ->and($document->items[0]->qty)->toBe('38.0000')
            ->and($document->items[0]->unit_price)->toBe('400.00')
            ->and($document->items[0]->line_total)->toBe('15200.00')
            ->and($document->total)->toBe('15200.00');
    });

    it('applies discount and rounding to the saved totals', function () {
        $this->post('/faktury', invoicePayload(null, [
            'discount_percent' => '10',
            'round_total' => true,
            'items' => [['name' => 'A', 'qty' => '1', 'unit' => 'ks', 'unit_price' => '1 234,55']],
        ]));

        $document = Document::firstOrFail();
        expect($document->subtotal)->toBe('1234.55')
            ->and($document->total)->toBe('1111.00')
            ->and($document->rounding)->toBe('-0.09')
            ->and($document->round_total)->toBeTrue();
    });

    it('saves a draft without a customer and without items', function () {
        $this->post('/faktury', invoicePayload(null, ['contact_id' => null, 'items' => []]))
            ->assertSessionDoesntHaveErrors();

        expect(Document::firstOrFail()->contact_id)->toBeNull();
    });

    it('stores the price list link of an item', function () {
        $priceItem = PriceItem::factory()->create(['name' => 'Logo']);

        $this->post('/faktury', invoicePayload(null, ['items' => [
            ['name' => 'Logo', 'description' => 'Tři koncepty', 'qty' => '1', 'unit' => 'ks', 'unit_price' => '12000', 'price_item_id' => $priceItem->id],
            ['name' => '', 'description' => '', 'qty' => '1', 'unit' => 'ks', 'unit_price' => '', 'price_item_id' => null],
        ]]));

        $items = Document::firstOrFail()->items;
        expect($items)->toHaveCount(1)
            ->and($items[0]->price_item_id)->toBe($priceItem->id)
            ->and($items[0]->description)->toBe('Tři koncepty');
    });

    it('validates in Czech', function () {
        $this->post('/faktury', invoicePayload(null, [
            'issue_date' => '25. 10. 2026',
            'due_date' => '2026-10-01',
            'discount_percent' => '120',
            'items' => [['name' => '', 'qty' => 'abc', 'unit' => 'ks', 'unit_price' => '10']],
        ]))->assertSessionHasErrors([
            'issue_date' => 'Zadejte datum ve tvaru 25. 10. 2026.',
            'discount_percent' => 'Sleva musí být mezi 0 a 100 %.',
            'items.0.name' => 'Zadejte popis položky.',
            'items.0.qty' => 'Počet musí být číslo.',
        ]);

        $this->post('/faktury', invoicePayload(null, ['due_date' => '2026-10-01']))
            ->assertSessionHasErrors(['due_date' => 'Splatnost nemůže být před datem vystavení.']);

        expect(Document::count())->toBe(0);
    });

    it('does not offer an archived contact', function () {
        $contact = Contact::factory()->create(['archived_at' => now()]);

        $this->post('/faktury', invoicePayload($contact))->assertSessionHasErrors('contact_id');
    });

    it('updates a draft and replaces its items', function () {
        $this->post('/faktury', invoicePayload());
        $document = Document::firstOrFail();

        $this->put("/faktury/{$document->id}", invoicePayload(null, [
            'contact_id' => $document->contact_id,
            'order_number' => 'JINÉ',
            'items' => [
                ['name' => 'Nová A', 'qty' => '2', 'unit' => 'ks', 'unit_price' => '100'],
                ['name' => 'Nová B', 'qty' => '1', 'unit' => 'ks', 'unit_price' => '50,50'],
            ],
        ]))->assertSessionDoesntHaveErrors();

        $document->refresh();
        expect($document->order_number)->toBe('JINÉ')
            ->and($document->items->pluck('name')->all())->toBe(['Nová A', 'Nová B'])
            ->and($document->total)->toBe('250.50');
    });

    it('opens a saved draft for editing', function () {
        $this->post('/faktury', invoicePayload());
        $document = Document::firstOrFail();

        $this->get("/faktury/{$document->id}/upravit")->assertInertia(fn (Assert $page) => $page
            ->where('document.id', $document->id)
            ->where('form.items.0.name', 'Správa webu')
            ->where('form.items.0.qty', '38')
            ->where('form.items.0.unit_price', '400.00')
            ->where('form.order_number', 'OBJ-7'));
    });
});

describe('locked documents', function () {
    it('does not open or change an issued invoice', function () {
        $document = issueInvoice(draftInvoice([[1, 1000]]));

        $this->get("/faktury/{$document->id}/upravit")
            ->assertRedirect("/faktury/{$document->id}")
            ->assertSessionHas('error');

        $this->put("/faktury/{$document->id}", invoicePayload(null, ['order_number' => 'ZMĚNA']))
            ->assertRedirect("/faktury/{$document->id}");

        expect($document->fresh()->order_number)->toBeNull()
            ->and($document->fresh()->items)->toHaveCount(1);
    });

    it('does not issue twice', function () {
        $document = issueInvoice(draftInvoice([[1, 1000]]));
        $number = $document->number;

        $this->post("/faktury/{$document->id}/vystavit", [])->assertRedirect("/faktury/{$document->id}");

        expect($document->fresh()->number)->toBe($number);
    });
});

describe('issuing', function () {
    it('saves and issues a new invoice with a number and a variable symbol', function () {
        prepareIssuing();

        $response = $this->post('/faktury/vystavit', invoicePayload());

        $document = Document::firstOrFail();
        $response->assertRedirect("/faktury/{$document->id}");
        expect($document->status)->toBe(DocumentStatus::Issued)
            ->and($document->number)->toMatch('/^2026\d{3}$/')
            ->and($document->vs)->toBe($document->number)
            ->and($document->isLocked())->toBeTrue()
            ->and($document->total)->toBe('15200.00')
            ->and($document->due_date->toDateString())->toBe('2026-11-08');
    });

    it('saves the form and issues an existing draft', function () {
        prepareIssuing();
        $this->post('/faktury', invoicePayload());
        $document = Document::firstOrFail();

        $this->post("/faktury/{$document->id}/vystavit", invoicePayload(null, [
            'contact_id' => $document->contact_id,
            'items' => [['name' => 'Upraveno', 'qty' => '1', 'unit' => 'ks', 'unit_price' => '999']],
        ]))->assertRedirect("/faktury/{$document->id}");

        $document->refresh();
        expect($document->status)->toBe(DocumentStatus::Issued)
            ->and($document->total)->toBe('999.00')
            ->and($document->items[0]->name)->toBe('Upraveno');
    });

    it('opens the issued invoice read-only', function () {
        $document = issueInvoice(draftInvoice([[2, 500]]));

        $this->get("/faktury/{$document->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->where('document.number', $document->number)
            ->where('document.vs', $document->vs)
            ->where('document.total', '1000.00')
            ->where('document.editable', false)
            ->has('document.items', 1));
    });

    it('keeps the draft and links to settings when the company is missing', function () {
        BankAccount::factory()->default()->create();
        NumberSeries::factory()->create();

        $this->post('/faktury/vystavit', invoicePayload())
            ->assertRedirect()
            ->assertSessionHas('error.href', '/nastaveni#firma');

        $document = Document::firstOrFail();
        expect($document->status)->toBe(DocumentStatus::Draft)
            ->and($document->items)->toHaveCount(1);
    });

    it('explains a missing number series', function () {
        CompanyProfile::factory()->create();
        BankAccount::factory()->default()->create();

        $this->post('/faktury/vystavit', invoicePayload())->assertSessionHas('error.href', '/nastaveni#rady');
        expect(Document::firstOrFail()->status)->toBe(DocumentStatus::Draft);
    });

    it('explains a missing bank account', function () {
        CompanyProfile::factory()->create();
        NumberSeries::factory()->create();

        $this->post('/faktury/vystavit', invoicePayload())->assertSessionHas('error.href', '/nastaveni#ucty');
    });

    it('does not need a bank account for a cash invoice', function () {
        CompanyProfile::factory()->create();
        NumberSeries::factory()->create();

        $this->post('/faktury/vystavit', invoicePayload(null, ['payment_method' => 'cash']))
            ->assertSessionMissing('error');

        expect(Document::firstOrFail()->status)->toBe(DocumentStatus::Issued);
    });

    it('explains missing items and a missing customer', function () {
        prepareIssuing();

        $this->post('/faktury/vystavit', invoicePayload(null, ['items' => []]))
            ->assertSessionHas('error.message', fn (string $message) => str_contains($message, 'aspoň jednu položku'));
        $this->post('/faktury/vystavit', invoicePayload(null, ['contact_id' => null]))
            ->assertSessionHas('error.message', fn (string $message) => str_contains($message, 'Vyberte odběratele'));
    });

    it('does not issue and does not save when validation fails', function () {
        prepareIssuing();

        $this->post('/faktury/vystavit', invoicePayload(null, ['due_date' => 'včera']))->assertSessionHasErrors('due_date');

        expect(Document::count())->toBe(0);
    });
});

describe('totals endpoint', function () {
    it('matches the document calculator for one line', function () {
        $this->postJson('/faktury/prepocet', [
            'items' => [['qty' => '38', 'unit_price' => '400,00']],
            'discount_percent' => '0',
            'round_total' => false,
        ])->assertOk()->assertJson([
            'lines' => ['15200.00'],
            'subtotal' => '15200.00',
            'discount_total' => '0.00',
            'rounding' => '0.00',
            'total' => '15200.00',
        ]);
    });

    it('matches the document calculator for several lines, discount and rounding', function () {
        $expected = app(DocumentCalculator::class)->calculate(
            [new CalculatorLine('1.5', '333.33'), new CalculatorLine('3', '19.99'), new CalculatorLine('1', '6800')],
            '7.5',
            true,
        );

        $this->postJson('/faktury/prepocet', [
            'items' => [
                ['qty' => '1,5', 'unit_price' => '333,33'],
                ['qty' => '3', 'unit_price' => '19,99'],
                ['qty' => '1', 'unit_price' => '6 800'],
            ],
            'discount_percent' => '7,5',
            'round_total' => true,
        ])->assertOk()->assertExactJson([
            'lines' => array_map('strval', $expected->lineTotals),
            'subtotal' => (string) $expected->subtotal,
            'discount_total' => (string) $expected->discountTotal,
            'vat_total' => (string) $expected->vatTotal,
            'rounding' => (string) $expected->rounding,
            'total' => (string) $expected->total,
        ]);
    });

    it('returns zeros without items and rejects bad numbers', function () {
        $this->postJson('/faktury/prepocet', ['items' => [], 'discount_percent' => '0', 'round_total' => false])
            ->assertOk()->assertJson(['total' => '0.00', 'lines' => []]);

        $this->postJson('/faktury/prepocet', ['items' => [['qty' => 'x', 'unit_price' => '1']], 'discount_percent' => '0', 'round_total' => false])
            ->assertJsonValidationErrors('items.0.qty');
        $this->postJson('/faktury/prepocet', ['items' => [], 'discount_percent' => '101', 'round_total' => false])
            ->assertJsonValidationErrors('discount_percent');
    });
});

describe('customer lookup', function () {
    it('searches only active contacts', function () {
        Contact::factory()->create(['name' => 'Novák s.r.o.', 'ico' => '27082440']);
        Contact::factory()->create(['name' => 'Novotný archiv', 'archived_at' => now()]);
        Contact::factory()->create(['name' => 'Svoboda']);

        $names = fn (string $q) => collect($this->getJson('/adresar/hledat?q='.urlencode($q))->json('contacts'))->pluck('name')->all();

        expect($names('nov'))->toBe(['Novák s.r.o.'])
            ->and($names('270 82'))->toBe(['Novák s.r.o.'])
            ->and($names(''))->toBe(['Novák s.r.o.', 'Svoboda']);
    });

    it('returns at most eight contacts with the fields for the customer card', function () {
        Contact::factory()->count(12)->create();

        $contacts = $this->getJson('/adresar/hledat')->json('contacts');

        expect($contacts)->toHaveCount(8)
            ->and($contacts[0])->toHaveKeys(['id', 'name', 'ico', 'dic', 'street', 'zip', 'city', 'email', 'default_due_days', 'default_discount_percent', 'ares_synced_at']);
    });

    it('creates a contact from ARES and returns it', function () {
        Http::fake(['ares.gov.cz/*' => Http::response([
            'ico' => '27082440',
            'obchodniJmeno' => 'Alza.cz a.s.',
            'dic' => 'CZ27082440',
            'sidlo' => ['kodStatu' => 'CZ', 'nazevObce' => 'Praha', 'nazevUlice' => 'Jankovcova', 'cisloDomovni' => 1522, 'cisloOrientacni' => 53, 'psc' => 17000],
        ])]);

        $this->postJson('/adresar/z-ares', ['ico' => '270 82 440'])
            ->assertCreated()
            ->assertJsonPath('contact.name', 'Alza.cz a.s.')
            ->assertJsonPath('created', true);

        $contact = Contact::firstOrFail();
        expect($contact->ico)->toBe('27082440')
            ->and($contact->dic)->toBe('CZ27082440')
            ->and($contact->city)->toBe('Praha')
            ->and($contact->ares_synced_at)->not->toBeNull();
    });

    it('returns an existing contact with the same ICO without asking ARES', function () {
        Http::fake();
        $contact = Contact::factory()->create(['ico' => '27082440']);

        $this->postJson('/adresar/z-ares', ['ico' => '27082440'])
            ->assertOk()
            ->assertJsonPath('contact.id', $contact->id)
            ->assertJsonPath('created', false);

        Http::assertNothingSent();
        expect(Contact::count())->toBe(1);
    });

    it('restores an archived contact with the same ICO', function () {
        $contact = Contact::factory()->create(['ico' => '27082440', 'archived_at' => now()]);

        $this->postJson('/adresar/z-ares', ['ico' => '27082440'])->assertOk();

        expect($contact->fresh()->archived_at)->toBeNull();
    });

    it('rejects an invalid or unknown ICO', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(['kod' => 'NENALEZENO'], 404)]);

        $this->postJson('/adresar/z-ares', ['ico' => '12345678'])->assertJsonValidationErrors('ico');
        $this->postJson('/adresar/z-ares', ['ico' => '27082440'])->assertJsonValidationErrors('ico');
        expect(Contact::count())->toBe(0);
    });
});

describe('deleting a draft', function () {
    it('deletes a draft', function () {
        $this->post('/faktury', invoicePayload());
        $document = Document::firstOrFail();

        $this->delete("/faktury/{$document->id}")->assertRedirect('/faktury');

        expect(Document::count())->toBe(0)
            ->and(DocumentItem::count())->toBe(0);
    });

    it('does not delete a paid invoice', function () {
        $document = issueInvoice(draftInvoice([[1, 1000]]));
        $document->forceFill(['status' => DocumentStatus::Paid, 'paid_total' => '1000.00'])->save();

        $this->delete("/faktury/{$document->id}")->assertSessionHas('error');

        expect(Document::count())->toBe(1);
    });
});
