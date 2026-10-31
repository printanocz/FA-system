<?php

use App\Domain\Documents\DocumentStatus;
use App\Domain\Documents\DocumentType;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function contactPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'company',
        'name' => 'Alza.cz a.s.',
        'ico' => '270 82 440',
        'dic' => 'cz27082440',
        'street' => 'Jankovcova 1522/53',
        'zip' => '170 00',
        'city' => 'Praha',
        'country' => '',
        'email' => 'Faktury@Alza.cz',
        'email_cc' => 'a@firma.cz, b@firma.cz;c@firma.cz',
        'phone' => '+420 123 456 789',
        'default_due_days' => '',
        'default_discount_percent' => '',
        'note' => '',
    ], $overrides);
}

function contactInvoice(Contact $contact, array $attributes = []): Document
{
    return Document::factory()->create(array_merge([
        'contact_id' => $contact->id,
        'type' => DocumentType::Invoice,
        'status' => DocumentStatus::Issued,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(14)->toDateString(),
        'total' => '1000.00',
        'paid_total' => '0.00',
    ], $attributes));
}

function aresResponse(array $override = []): array
{
    return array_merge([
        'ico' => '27082440',
        'obchodniJmeno' => 'Alza.cz a.s.',
        'dic' => 'CZ27082440',
        'sidlo' => ['kodStatu' => 'CZ', 'nazevObce' => 'Praha', 'nazevUlice' => 'Jankovcova', 'cisloDomovni' => 1522, 'cisloOrientacni' => 53, 'psc' => 17000],
    ], $override);
}

beforeEach(function () {
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

describe('access', function () {
    it('redirects guests to login', function () {
        auth()->logout();
        $this->get('/adresar')->assertRedirect('/login');
    });

    it('renders the address book', function () {
        Contact::factory()->create(['name' => 'Beta']);

        $this->get('/adresar')->assertInertia(fn (Assert $page) => $page
            ->component('Contacts/Index')
            ->has('contacts', 1)
            ->where('selectedId', null)
            ->where('selected.name', 'Beta'));
    });

    it('selects an explicit contact', function () {
        Contact::factory()->create(['name' => 'Alfa']);
        $beta = Contact::factory()->create(['name' => 'Beta']);

        $this->get("/adresar/{$beta->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selectedId', $beta->id)
            ->where('selected.name', 'Beta'));
    });
});

describe('search and order', function () {
    it('searches by name, ICO and e-mail', function () {
        Contact::factory()->create(['name' => 'Novák s.r.o.', 'ico' => '27082440', 'email' => 'a@x.cz']);
        Contact::factory()->create(['name' => 'Svoboda', 'ico' => '45274649', 'email' => 'fakturace@svoboda.cz']);

        $names = fn (string $q) => collect($this->get('/adresar?q='.urlencode($q))->viewData('page')['props']['contacts'])->pluck('name')->all();

        expect($names('nov'))->toBe(['Novák s.r.o.'])
            ->and($names('270 82'))->toBe(['Novák s.r.o.'])
            ->and($names('45274649'))->toBe(['Svoboda'])
            ->and($names('fakturace@'))->toBe(['Svoboda'])
            ->and($names('nic'))->toBe([]);
    });

    it('treats LIKE wildcards literally', function () {
        Contact::factory()->create(['name' => 'Novák']);

        expect($this->get('/adresar?q=%25')->viewData('page')['props']['contacts'])->toBe([]);
    });

    it('orders by turnover this year, then alphabetically', function () {
        $a = Contact::factory()->create(['name' => 'Alfa']);
        $b = Contact::factory()->create(['name' => 'Beta']);
        $c = Contact::factory()->create(['name' => 'Cecil']);
        Contact::factory()->create(['name' => 'Delta']);
        contactInvoice($b, ['total' => '5000.00']);
        contactInvoice($c, ['total' => '9000.00']);
        contactInvoice($a, ['total' => '9000.00', 'issue_date' => now()->subYear()->toDateString()]);

        $page = $this->get('/adresar')->viewData('page')['props']['contacts'];

        expect(collect($page)->pluck('name')->all())->toBe(['Cecil', 'Beta', 'Alfa', 'Delta']);
    });

    it('hides archived contacts unless asked', function () {
        Contact::factory()->create(['name' => 'Aktivní']);
        Contact::factory()->create(['name' => 'Starý', 'archived_at' => now()]);

        expect($this->get('/adresar')->viewData('page')['props']['contacts'])->toHaveCount(1)
            ->and($this->get('/adresar?archivovane=1')->viewData('page')['props']['contacts'])->toHaveCount(2);
    });
});

describe('create and update', function () {
    it('creates a normalised contact and shows it', function () {
        $response = $this->post('/adresar', contactPayload());

        $contact = Contact::firstOrFail();
        $response->assertRedirect("/adresar/{$contact->id}");
        expect($contact->ico)->toBe('27082440')
            ->and($contact->dic)->toBe('CZ27082440')
            ->and($contact->country)->toBe('CZ')
            ->and($contact->email)->toBe('faktury@alza.cz')
            ->and($contact->email_cc)->toBe(['a@firma.cz', 'b@firma.cz', 'c@firma.cz'])
            ->and($contact->default_due_days)->toBeNull();
    });

    it('requires a name and a valid type', function () {
        $this->post('/adresar', contactPayload(['name' => '', 'type' => 'x']))
            ->assertSessionHasErrors(['name', 'type']);
    });

    it('rejects an invalid ICO', function () {
        $this->post('/adresar', contactPayload(['ico' => '12345678']))
            ->assertSessionHasErrors(['ico' => 'Zadejte platné IČO (8 číslic s kontrolním součtem).']);
        expect(Contact::count())->toBe(0);
    });

    it('rejects an invalid DIC', function () {
        $this->post('/adresar', contactPayload(['dic' => 'CZ12']))
            ->assertSessionHasErrors(['dic' => 'Zadejte platné DIČ ve tvaru CZ a 8 až 10 číslic.']);
    });

    it('rejects invalid copy addresses, due days and discount', function () {
        $this->post('/adresar', contactPayload([
            'email_cc' => 'ok@firma.cz, neplatny',
            'default_due_days' => '999',
            'default_discount_percent' => '120',
        ]))->assertSessionHasErrors(['email_cc.1', 'default_due_days', 'default_discount_percent']);
    });

    it('accepts empty optional fields and a comma discount', function () {
        $this->post('/adresar', contactPayload([
            'ico' => '', 'dic' => '', 'email' => '', 'email_cc' => '',
            'default_due_days' => '14', 'default_discount_percent' => '2,5',
        ]))->assertSessionDoesntHaveErrors();

        $contact = Contact::firstOrFail();
        expect($contact->ico)->toBeNull()
            ->and($contact->email_cc)->toBeNull()
            ->and($contact->default_due_days)->toBe(14)
            ->and((float) $contact->default_discount_percent)->toBe(2.5);
    });

    it('updates a contact', function () {
        $contact = Contact::factory()->create();

        $this->put("/adresar/{$contact->id}", contactPayload(['name' => 'Nový název']))
            ->assertSessionDoesntHaveErrors();

        expect($contact->fresh()->name)->toBe('Nový název');
    });
});

describe('ARES', function () {
    it('suggests values for the form without saving', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(aresResponse())]);

        $this->postJson('/adresar/ares', ['ico' => '27082440'])
            ->assertOk()
            ->assertJson(['name' => 'Alza.cz a.s.', 'ico' => '27082440', 'dic' => 'CZ27082440', 'city' => 'Praha', 'country' => 'CZ']);

        expect(Contact::count())->toBe(0);
    });

    it('omits the DIC when ARES does not return it', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(aresResponse(['dic' => null]))]);

        $this->postJson('/adresar/ares', ['ico' => '27082440'])
            ->assertOk()
            ->assertJsonMissingPath('dic');
    });

    it('rejects an invalid ICO and an unknown subject', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(['kod' => 'NENALEZENO'], 404)]);

        $this->postJson('/adresar/ares', ['ico' => '12345678'])->assertJsonValidationErrors('ico');
        $this->postJson('/adresar/ares', ['ico' => '27082440'])->assertJsonValidationErrors('ico');
    });

    it('refreshes a contact from ARES and keeps the DIC ARES omits', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(aresResponse(['dic' => null, 'obchodniJmeno' => 'Alza a.s.']))]);
        $contact = Contact::factory()->create(['name' => 'Starý', 'ico' => '27082440', 'dic' => 'CZ27082440', 'city' => 'Brno']);

        $this->post("/adresar/{$contact->id}/ares")->assertSessionDoesntHaveErrors();

        $contact->refresh();
        expect($contact->name)->toBe('Alza a.s.')
            ->and($contact->city)->toBe('Praha')
            ->and($contact->dic)->toBe('CZ27082440')
            ->and($contact->ares_synced_at)->not->toBeNull();
    });

    it('overwrites the DIC when ARES returns one', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(aresResponse())]);
        $contact = Contact::factory()->create(['ico' => '27082440', 'dic' => null]);

        $this->post("/adresar/{$contact->id}/ares");

        expect($contact->fresh()->dic)->toBe('CZ27082440');
    });

    it('fails without an ICO and when ARES is unavailable', function () {
        Http::fake(['ares.gov.cz/*' => Http::response('', 500)]);
        $noIco = Contact::factory()->create(['ico' => null]);
        $withIco = Contact::factory()->create(['ico' => '27082440', 'name' => 'Beze změny']);

        $this->post("/adresar/{$noIco->id}/ares")->assertSessionHasErrors('ico');
        $this->post("/adresar/{$withIco->id}/ares")->assertSessionHasErrors('ico');
        expect($withIco->fresh()->name)->toBe('Beze změny');
    });
});

describe('delete and archive', function () {
    it('deletes a contact without documents', function () {
        $contact = Contact::factory()->create();

        $this->delete("/adresar/{$contact->id}")->assertRedirect('/adresar');

        expect(Contact::count())->toBe(0);
    });

    it('archives a contact that has any document', function () {
        $contact = Contact::factory()->create();
        Document::factory()->create(['contact_id' => $contact->id]);

        $this->delete("/adresar/{$contact->id}")->assertRedirect('/adresar');

        expect($contact->fresh()->archived_at)->not->toBeNull();
    });

    it('restores an archived contact', function () {
        $contact = Contact::factory()->create(['archived_at' => now()]);

        $this->post("/adresar/{$contact->id}/obnovit");

        expect($contact->fresh()->archived_at)->toBeNull();
    });
});

describe('statistics', function () {
    it('counts turnover of this year from issued invoices only', function () {
        $contact = Contact::factory()->create();
        contactInvoice($contact, ['total' => '1000.00']);
        contactInvoice($contact, ['total' => '2000.50', 'status' => DocumentStatus::Paid, 'paid_total' => '2000.50', 'paid_at' => now()->toDateString()]);
        contactInvoice($contact, ['total' => '700.00', 'status' => DocumentStatus::Draft]);
        contactInvoice($contact, ['total' => '800.00', 'status' => DocumentStatus::Cancelled]);
        contactInvoice($contact, ['total' => '900.00', 'type' => DocumentType::Quote]);
        contactInvoice($contact, ['total' => '5000.00', 'issue_date' => now()->subYear()->toDateString()]);

        $this->get("/adresar/{$contact->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selected.stats.turnover', '3000.50')
            ->where('selected.stats.invoice_count', 2)
            ->where('year', (int) now()->format('Y')));
    });

    it('sums unpaid amounts and the earliest due date', function () {
        $contact = Contact::factory()->create();
        contactInvoice($contact, ['total' => '1000.00', 'paid_total' => '400.00', 'status' => DocumentStatus::PartiallyPaid, 'due_date' => '2030-05-20']);
        contactInvoice($contact, ['total' => '500.00', 'due_date' => '2030-05-10']);
        contactInvoice($contact, ['total' => '9999.00', 'status' => DocumentStatus::Paid, 'paid_total' => '9999.00', 'paid_at' => now()->toDateString()]);

        $this->get("/adresar/{$contact->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selected.stats.unpaid', '1100.00')
            ->where('selected.stats.unpaid_next_due', '2030-05-10'));
    });

    it('averages days from issue to payment of paid invoices', function () {
        $contact = Contact::factory()->create();
        contactInvoice($contact, ['status' => DocumentStatus::Paid, 'paid_total' => '1000.00', 'issue_date' => '2026-01-01', 'paid_at' => '2026-01-11']);
        contactInvoice($contact, ['status' => DocumentStatus::Paid, 'paid_total' => '1000.00', 'issue_date' => '2026-02-01', 'paid_at' => '2026-02-06']);
        contactInvoice($contact, ['status' => DocumentStatus::Paid, 'paid_total' => '1000.00', 'issue_date' => '2026-03-01', 'paid_at' => '2026-03-04']);
        contactInvoice($contact);

        $this->get("/adresar/{$contact->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selected.stats.average_payment_days', 6));
    });

    it('has no average without paid invoices', function () {
        $contact = Contact::factory()->create();
        contactInvoice($contact);

        $this->get("/adresar/{$contact->id}")->assertInertia(fn (Assert $page) => $page
            ->where('selected.stats.average_payment_days', null));
    });

    it('lists the last ten documents', function () {
        $contact = Contact::factory()->create();
        foreach (range(1, 12) as $i) {
            contactInvoice($contact, ['total' => (string) $i]);
        }

        $this->get("/adresar/{$contact->id}")->assertInertia(fn (Assert $page) => $page
            ->has('selected.documents', 10)
            ->where('selected.has_documents', true));
    });
});
