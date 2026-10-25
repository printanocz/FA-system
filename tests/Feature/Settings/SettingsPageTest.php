<?php

use App\Domain\Settings\DocumentSettings;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\NumberSeries;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function settingsUser()
{
    [$user] = twoFactorUser();

    return $user;
}

function companyPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Adam Fuják',
        'ico' => '27082440',
        'dic' => '',
        'street' => 'Krásné Loučky 27/27',
        'zip' => '794 01',
        'city' => 'Krnov',
        'registration_note' => 'Fyzická osoba zapsaná v živnostenském rejstříku.',
        'vat_payer' => false,
        'email' => 'jsem@adamfujak.cz',
        'phone' => '775 549 910',
        'web' => 'www.adamfujak.cz',
    ], $overrides);
}

function documentsPayload(array $overrides = []): array
{
    return array_merge([
        'due_days' => 30,
        'constant_symbol' => '0308',
        'round_total' => true,
        'text_before' => 'Fakturuji Vám za dodané služby:',
        'text_after' => 'Děkuji za platbu včas.',
    ], $overrides);
}

describe('access', function () {
    it('sends a guest to the login', function () {
        $this->get('/nastaveni')->assertRedirect('/login');
    });

    it('sends a user without 2FA to the 2FA setup', function () {
        $this->actingAs(User::factory()->create())->get('/nastaveni')->assertRedirect(route('two-factor.setup'));
    });
});

describe('page', function () {
    it('renders the settings with the saved data', function () {
        $this->seed(InitialSetupSeeder::class);
        CompanyProfile::factory()->create(['name' => 'Adam Fuják', 'ico' => '27082440']);
        BankAccount::factory()->default()->create(['name' => 'Česká spořitelna']);

        $this->actingAs(settingsUser())->get('/nastaveni')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('company.name', 'Adam Fuják')
                ->where('company.vat_payer', false)
                ->where('documents.due_days', 14)
                ->where('documents.constant_symbol', '0308')
                ->where('documents.text_before', 'Fakturuji Vám za dodané služby:')
                ->has('bankAccounts', 1)
                ->where('bankAccounts.0.is_default', true)
                ->has('numberSeries', 2)
                ->where('numberSeries.0.name', 'Faktury vydané')
                ->where('numberSeries.0.preview', now()->format('Y').'001')
                ->where('account.twoFactor', true));
    });

    it('renders empty forms on a fresh installation', function () {
        $this->actingAs(settingsUser())->get('/nastaveni')
            ->assertInertia(fn (Assert $page) => $page
                ->where('company.name', '')
                ->where('documents.due_days', 14)
                ->has('bankAccounts', 0)
                ->has('numberSeries', 0));
    });

    it('shares the signed-in user for the sidebar', function () {
        $user = settingsUser();

        $this->actingAs($user)->get('/nastaveni')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.name', $user->name)
                ->where('auth.user.email', $user->email));
    });
});

describe('company and documents', function () {
    it('saves the company profile and the document defaults', function () {
        $this->actingAs(settingsUser())
            ->from('/nastaveni')
            ->put('/nastaveni', ['company' => companyPayload(), 'documents' => documentsPayload()])
            ->assertRedirect('/nastaveni')
            ->assertSessionHasNoErrors();

        $company = CompanyProfile::current();
        $settings = app(DocumentSettings::class);

        expect($company->name)->toBe('Adam Fuják')
            ->and($company->ico)->toBe('27082440')
            ->and($company->dic)->toBeNull()
            ->and($company->city)->toBe('Krnov')
            ->and($company->vat_payer)->toBeFalse()
            ->and($settings->dueDays())->toBe(30)
            ->and($settings->constantSymbol())->toBe('0308')
            ->and($settings->roundTotal())->toBeTrue()
            ->and($settings->textAfter())->toBe('Děkuji za platbu včas.');
    });

    it('updates an existing profile', function () {
        $this->actingAs(settingsUser());
        $this->put('/nastaveni', ['company' => companyPayload(), 'documents' => documentsPayload()]);

        $this->put('/nastaveni', ['company' => companyPayload(['city' => 'Ostrava', 'dic' => 'CZ27082440']), 'documents' => documentsPayload(['due_days' => 7])]);

        expect(CompanyProfile::query()->count())->toBe(1)
            ->and(CompanyProfile::current()->city)->toBe('Ostrava')
            ->and(CompanyProfile::current()->dic)->toBe('CZ27082440')
            ->and(app(DocumentSettings::class)->dueDays())->toBe(7);
    });

    it('rejects an invalid IČO and DIČ with Czech messages', function () {
        $response = $this->actingAs(settingsUser())
            ->from('/nastaveni')
            ->put('/nastaveni', ['company' => companyPayload(['ico' => '27082441', 'dic' => '27082440']), 'documents' => documentsPayload()]);

        $response->assertRedirect('/nastaveni')->assertSessionHasErrors(['company.ico', 'company.dic']);

        expect(session('errors')->first('company.ico'))->toContain('IČO')
            ->and(session('errors')->first('company.dic'))->toContain('DIČ')
            ->and(CompanyProfile::current())->toBeNull();
    });

    it('requires the company name', function () {
        $this->actingAs(settingsUser())
            ->put('/nastaveni', ['company' => companyPayload(['name' => '']), 'documents' => documentsPayload()])
            ->assertSessionHasErrors('company.name');
    });

    it('reports errors of the documents part and saves nothing at all', function () {
        $this->actingAs(settingsUser())
            ->put('/nastaveni', ['company' => companyPayload(), 'documents' => documentsPayload(['constant_symbol' => 'KS', 'due_days' => 999])])
            ->assertSessionHasErrors(['documents.constant_symbol', 'documents.due_days']);

        expect(CompanyProfile::current())->toBeNull()
            ->and(app(DocumentSettings::class)->dueDays())->toBe(14);
    });

    it('reports both parts at once', function () {
        $this->actingAs(settingsUser())
            ->put('/nastaveni', ['company' => companyPayload(['ico' => '1']), 'documents' => documentsPayload(['due_days' => -5])])
            ->assertSessionHasErrors(['company.ico', 'documents.due_days']);
    });
});

describe('ARES', function () {
    it('suggests the values without saving them', function () {
        Http::fake(['ares.gov.cz/*' => Http::response([
            'ico' => '27082440',
            'obchodniJmeno' => 'Alza.cz a.s.',
            'dic' => 'CZ27082440',
            'sidlo' => ['kodStatu' => 'CZ', 'nazevObce' => 'Praha', 'nazevUlice' => 'Jankovcova', 'cisloDomovni' => 1522, 'cisloOrientacni' => 53, 'psc' => 17000],
        ])]);

        $this->actingAs(settingsUser())->postJson('/nastaveni/ares', ['ico' => '270 82 440'])
            ->assertOk()
            ->assertJson([
                'name' => 'Alza.cz a.s.',
                'ico' => '27082440',
                'dic' => 'CZ27082440',
                'street' => 'Jankovcova 1522/53',
                'city' => 'Praha',
                'zip' => '17000',
            ]);

        expect(CompanyProfile::current())->toBeNull();
    });

    it('omits the VAT number when ARES has none', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(['ico' => '27082440', 'obchodniJmeno' => 'Test', 'sidlo' => ['nazevObce' => 'Praha', 'psc' => 11000]])]);

        $this->actingAs(settingsUser())->postJson('/nastaveni/ares', ['ico' => '27082440'])
            ->assertOk()
            ->assertJsonMissing(['dic' => 'CZ27082440'])
            ->assertJsonMissingPath('dic');
    });

    it('reports an unknown subject as an error of the IČO field', function () {
        Http::fake(['ares.gov.cz/*' => Http::response([], 404)]);

        $this->actingAs(settingsUser())->postJson('/nastaveni/ares', ['ico' => '27082440'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ico');
    });

    it('rejects an invalid IČO without calling ARES', function () {
        Http::fake();

        $this->actingAs(settingsUser())->postJson('/nastaveni/ares', ['ico' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ico');

        Http::assertNothingSent();
    });

    it('reports an unavailable ARES', function () {
        Http::fake(['ares.gov.cz/*' => Http::response('', 503)]);

        $this->actingAs(settingsUser())->postJson('/nastaveni/ares', ['ico' => '27082440'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ico');
    });

    it('requires the IČO', function () {
        $this->actingAs(settingsUser())->postJson('/nastaveni/ares', [])->assertJsonValidationErrors('ico');
    });
});

describe('bank accounts', function () {
    it('adds an account with the computed IBAN and makes the first one default', function () {
        $this->actingAs(settingsUser())->post('/nastaveni/ucty', [
            'name' => 'Česká spořitelna · podnikání',
            'account_number' => '2401234567',
            'bank_code' => '0800',
        ])->assertSessionHasNoErrors();

        $account = BankAccount::query()->firstOrFail();

        expect($account->iban)->toBe('CZ1508000000002401234567')->and($account->is_default)->toBeTrue();
    });

    it('moves the default flag to another account', function () {
        $this->actingAs(settingsUser());
        $this->post('/nastaveni/ucty', ['name' => 'První', 'account_number' => '2401234567', 'bank_code' => '0800']);
        $this->post('/nastaveni/ucty', ['name' => 'Druhý', 'account_number' => '19-2000145399', 'bank_code' => '0800', 'is_default' => true]);

        expect(BankAccount::query()->where('name', 'První')->first()->is_default)->toBeFalse()
            ->and(BankAccount::query()->where('name', 'Druhý')->first()->is_default)->toBeTrue()
            ->and(BankAccount::query()->where('is_default', true)->count())->toBe(1);
    });

    it('edits an account and recomputes the IBAN', function () {
        $this->actingAs(settingsUser());
        $this->post('/nastaveni/ucty', ['name' => 'První', 'account_number' => '2401234567', 'bank_code' => '0800']);
        $account = BankAccount::query()->firstOrFail();

        $this->put("/nastaveni/ucty/{$account->id}", ['name' => 'Přejmenovaný', 'account_number' => '19-2000145399', 'bank_code' => '0800', 'swift' => 'gibaczpx'])
            ->assertSessionHasNoErrors();

        $account->refresh();
        expect($account->name)->toBe('Přejmenovaný')
            ->and($account->iban)->toBe('CZ6508000000192000145399')
            ->and($account->swift)->toBe('GIBACZPX');
    });

    it('validates the account', function () {
        $this->actingAs(settingsUser())
            ->post('/nastaveni/ucty', ['name' => '', 'account_number' => 'abc', 'bank_code' => '8'])
            ->assertSessionHasErrors(['name', 'bank_code']);

        expect(BankAccount::query()->count())->toBe(0);
    });

    it('deletes an account and promotes another to default', function () {
        $this->actingAs(settingsUser());
        $this->post('/nastaveni/ucty', ['name' => 'První', 'account_number' => '2401234567', 'bank_code' => '0800']);
        $this->post('/nastaveni/ucty', ['name' => 'Druhý', 'account_number' => '19-2000145399', 'bank_code' => '0800', 'is_default' => true]);

        $this->delete('/nastaveni/ucty/'.BankAccount::query()->where('name', 'Druhý')->first()->id);

        expect(BankAccount::query()->count())->toBe(1)
            ->and(BankAccount::query()->first()->is_default)->toBeTrue();
    });
});

describe('number series', function () {
    beforeEach(fn () => $this->seed(InitialSetupSeeder::class));

    it('moves the next number forward and shows the preview', function () {
        $series = NumberSeries::query()->where('prefix', '')->firstOrFail();
        $this->actingAs(settingsUser())
            ->put("/nastaveni/rady/{$series->id}", ['next_number' => 61])
            ->assertSessionHasNoErrors();

        $this->get('/nastaveni')->assertInertia(fn (Assert $page) => $page
            ->where('numberSeries.0.next_number', 61)
            ->where('numberSeries.0.preview', now()->format('Y').'061'));
    });

    it('issues 2026061 from a series moved to 61', function () {
        $series = NumberSeries::query()->where('prefix', '')->firstOrFail();
        $this->actingAs(settingsUser())->put("/nastaveni/rady/{$series->id}", ['next_number' => 61]);

        expect(issueInvoice(draftInvoice(), '2026-03-10')->number)->toBe('2026061');
    });

    it('changes the name, prefix and format', function () {
        $series = NumberSeries::query()->where('prefix', 'N')->firstOrFail();

        $this->actingAs(settingsUser())->put("/nastaveni/rady/{$series->id}", ['name' => 'Nabídky', 'prefix' => 'NAB', 'format' => '{YY}{NNNN}', 'reset_yearly' => false])
            ->assertSessionHasNoErrors();

        $series->refresh();
        expect($series->name)->toBe('Nabídky')->and($series->prefix)->toBe('NAB')->and($series->format)->toBe('{YY}{NNNN}')->and($series->reset_yearly)->toBeFalse();
    });

    it('refuses to go below an already allocated number with a Czech error on the field', function () {
        $series = NumberSeries::query()->where('prefix', '')->firstOrFail();
        issueInvoice(draftInvoice(), '2026-03-10');
        issueInvoice(draftInvoice(), '2026-03-11');

        $response = $this->actingAs(settingsUser())->from('/nastaveni')->put("/nastaveni/rady/{$series->id}", ['next_number' => 1]);

        $response->assertRedirect('/nastaveni')->assertSessionHasErrors('next_number');
        expect(session('errors')->first('next_number'))->toContain('nelze snížit pod 3')
            ->and($series->fresh()->next_number)->toBe(3);
    });

    it('validates the format and the name', function () {
        $series = NumberSeries::query()->firstOrFail();

        $this->actingAs(settingsUser())->put("/nastaveni/rady/{$series->id}", ['format' => '{YYYY}', 'name' => ''])
            ->assertSessionHasErrors(['format', 'name']);
    });
});

describe('account and security', function () {
    it('changes the password', function () {
        $user = settingsUser();

        $this->actingAs($user)->from('/nastaveni')->put('/user/password', [
            'current_password' => 'password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect('/nastaveni')->assertSessionHasNoErrors();

        expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
    });

    it('rejects a wrong current password in Czech', function () {
        $user = settingsUser();

        $this->actingAs($user)->from('/nastaveni')->put('/user/password', [
            'current_password' => 'wrong',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('current_password', null, 'updatePassword');

        expect(session('errors')->getBag('updatePassword')->first('current_password'))->toBe('Zadané heslo není správné.')
            ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });

    it('rejects a short or unconfirmed new password', function () {
        $this->actingAs(settingsUser())->put('/user/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'other',
        ])->assertSessionHasErrors('password', null, 'updatePassword');
    });

    it('asks for the password before the recovery codes', function () {
        $this->actingAs(settingsUser())->get('/nastaveni/zalozni-kody')->assertRedirect(route('password.confirm'));
    });

    it('shows the recovery codes after the password was confirmed', function () {
        $this->actingAs(settingsUser())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/nastaveni/zalozni-kody')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/RecoveryCodes')
                ->where('recoveryCodes', ['recovery-one', 'recovery-two']));
    });

    it('logs the user out', function () {
        $this->actingAs(settingsUser())->post('/logout')->assertRedirect();

        $this->assertGuest();
    });
});
