<?php

use App\Actions\Settings\UpdateCompanyProfile;
use App\Models\CompanyProfile;
use App\Services\Ares\Exceptions\AresSubjectNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function companyInput(array $overrides = []): array
{
    return array_merge([
        'name' => 'Adam Fujak',
        'ico' => '27082440',
        'dic' => 'CZ27082440',
        'street' => 'Hlavní 1',
        'city' => 'Krnov',
        'zip' => '79401',
        'country' => 'CZ',
        'registration_note' => 'Fyzická osoba zapsaná v živnostenském rejstříku.',
        'email' => 'adam@example.com',
        'phone' => '+420 123 456 789',
        'web' => 'adamfujak.cz',
        'vat_payer' => false,
    ], $overrides);
}

beforeEach(fn () => $this->update = app(UpdateCompanyProfile::class));

it('creates the company profile', function () {
    $profile = ($this->update)(companyInput());

    expect(CompanyProfile::query()->count())->toBe(1)
        ->and($profile->name)->toBe('Adam Fujak')
        ->and($profile->vat_payer)->toBeFalse();
});

it('updates the single profile instead of adding another', function () {
    ($this->update)(companyInput());
    ($this->update)(companyInput(['name' => 'Adam Fujak, OSVČ', 'city' => 'Ostrava']));

    $profile = CompanyProfile::current();

    expect(CompanyProfile::query()->count())->toBe(1)
        ->and($profile->name)->toBe('Adam Fujak, OSVČ')
        ->and($profile->city)->toBe('Ostrava');
});

it('normalizes IČO, DIČ and country', function () {
    $profile = ($this->update)(companyInput(['ico' => '270 82 440', 'dic' => ' cz27082440 ', 'country' => 'cz']));

    expect($profile->ico)->toBe('27082440')->and($profile->dic)->toBe('CZ27082440')->and($profile->country)->toBe('CZ');
});

it('turns blank optional fields into null', function () {
    $profile = ($this->update)(companyInput(['dic' => '', 'phone' => '  ', 'web' => '']));

    expect($profile->dic)->toBeNull()->and($profile->phone)->toBeNull()->and($profile->web)->toBeNull();
});

it('rejects invalid input', function (array $override, string $field) {
    try {
        ($this->update)(companyInput($override));
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field)
            ->and(CompanyProfile::query()->count())->toBe(0);

        return;
    }

    $this->fail('Validation did not fail.');
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad IČO checksum' => [['ico' => '27082441'], 'ico'],
    'short IČO' => [['ico' => '123'], 'ico'],
    'DIČ without CZ' => [['dic' => '27082440'], 'dic'],
    'bad e-mail' => [['email' => 'adam'], 'email'],
    'country of three letters' => [['country' => 'CZE'], 'country'],
]);

it('reports the failing IČO in Czech', function () {
    try {
        ($this->update)(companyInput(['ico' => '1']));
    } catch (ValidationException $e) {
        expect($e->errors()['ico'][0])->toContain('IČO');

        return;
    }

    $this->fail('Validation did not fail.');
});

describe('loading from ARES', function () {
    function aresBody(array $override = []): array
    {
        return array_merge([
            'ico' => '27082440',
            'obchodniJmeno' => 'Alza.cz a.s.',
            'dic' => 'CZ27082440',
            'sidlo' => ['kodStatu' => 'CZ', 'nazevObce' => 'Praha', 'nazevUlice' => 'Jankovcova', 'cisloDomovni' => 1522, 'cisloOrientacni' => 53, 'psc' => 17000],
        ], $override);
    }

    it('returns values for the form without saving', function () {
        Http::fake(['ares.gov.cz/*' => Http::response(aresBody())]);

        $values = $this->update->suggestFromAres('27082440');

        expect($values)->toBe([
            'name' => 'Alza.cz a.s.',
            'ico' => '27082440',
            'dic' => 'CZ27082440',
            'street' => 'Jankovcova 1522/53',
            'city' => 'Praha',
            'zip' => '17000',
            'country' => 'CZ',
        ])->and(CompanyProfile::query()->count())->toBe(0);
    });

    it('leaves the VAT number out when ARES has none', function () {
        $body = aresBody();
        unset($body['dic']);
        Http::fake(['ares.gov.cz/*' => Http::response($body)]);

        expect($this->update->suggestFromAres('27082440'))->not->toHaveKey('dic');
    });

    it('passes ARES errors on', function () {
        Http::fake(['ares.gov.cz/*' => Http::response([], 404)]);

        $this->update->suggestFromAres('27082440');
    })->throws(AresSubjectNotFoundException::class);
});
