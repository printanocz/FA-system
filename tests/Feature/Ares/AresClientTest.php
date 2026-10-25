<?php

use App\Models\Contact;
use App\Services\Ares\AresClient;
use App\Services\Ares\AresSubject;
use App\Services\Ares\Exceptions\AresSubjectNotFoundException;
use App\Services\Ares\Exceptions\AresUnavailableException;
use App\Services\Ares\Exceptions\InvalidIcoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const ARES_URL = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/27082440';

/** Zkrácená, ale tvarem věrná odpověď ARES pro Alza.cz a.s. */
function aresPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'ico' => '27082440',
        'obchodniJmeno' => 'Alza.cz a.s.',
        'sidlo' => [
            'kodStatu' => 'CZ',
            'nazevStatu' => 'Česká republika',
            'kodKraje' => 19,
            'nazevKraje' => 'Hlavní město Praha',
            'kodObce' => 554782,
            'nazevObce' => 'Praha',
            'nazevMestskehoObvodu' => 'Praha 7',
            'nazevUlice' => 'Jankovcova',
            'cisloDomovni' => 1522,
            'cisloOrientacni' => 53,
            'nazevCastiObce' => 'Holešovice',
            'psc' => 17000,
            'textovaAdresa' => 'Jankovcova 1522/53, Holešovice, 17000 Praha 7',
        ],
        'pravniForma' => '121',
        'datumVzniku' => '2003-08-26',
        'dic' => 'CZ27082440',
        'czNace' => ['47911', '56100'],
    ], $overrides);
}

beforeEach(function () {
    Cache::flush();
    $this->ares = app(AresClient::class);
});

it('maps the ARES response to a subject', function () {
    Http::fake([ARES_URL => Http::response(aresPayload())]);

    expect($this->ares->find('27082440')->toArray())->toBe([
        'name' => 'Alza.cz a.s.',
        'ico' => '27082440',
        'dic' => 'CZ27082440',
        'street' => 'Jankovcova 1522/53',
        'city' => 'Praha',
        'zip' => '17000',
        'country' => 'CZ',
    ]);
});

it('calls the documented endpoint with a 5 second timeout', function () {
    Http::fake([ARES_URL => Http::response(aresPayload())]);

    $this->ares->find('27082440');

    Http::assertSent(fn (Request $request) => $request->url() === ARES_URL
        && $request->method() === 'GET'
        && $request->hasHeader('Accept', 'application/json'));
});

it('accepts an IČO typed with spaces', function () {
    Http::fake([ARES_URL => Http::response(aresPayload())]);

    expect($this->ares->find('270 82 440')->ico)->toBe('27082440');
});

it('joins the orientation letter to the house number', function () {
    Http::fake([ARES_URL => Http::response(aresPayload(['sidlo' => ['cisloOrientacniPismeno' => 'a']]))]);

    expect($this->ares->find('27082440')->street)->toBe('Jankovcova 1522/53a');
});

it('uses the village part when there is no street', function () {
    $payload = aresPayload();
    unset($payload['sidlo']['nazevUlice'], $payload['sidlo']['cisloOrientacni']);
    $payload['sidlo'] = array_merge($payload['sidlo'], ['nazevObce' => 'Krásné Loučky', 'nazevCastiObce' => 'Krásné Loučky', 'cisloDomovni' => 12, 'psc' => 79401]);
    Http::fake([ARES_URL => Http::response($payload)]);

    $subject = $this->ares->find('27082440');

    expect($subject->street)->toBe('Krásné Loučky 12')->and($subject->city)->toBe('Krásné Loučky');
});

it('restores the leading zero of a ZIP code', function () {
    Http::fake([ARES_URL => Http::response(aresPayload(['sidlo' => ['psc' => 1000]]))]);

    expect($this->ares->find('27082440')->zip)->toBe('01000');
});

it('leaves the VAT number empty when the subject has none', function () {
    $payload = aresPayload();
    unset($payload['dic']);
    Http::fake([ARES_URL => Http::response($payload)]);

    expect($this->ares->find('27082440')->dic)->toBeNull();
});

it('caches a successful lookup for 24 hours', function () {
    Http::fake([ARES_URL => Http::response(aresPayload())]);

    $this->ares->find('27082440');
    $this->ares->find('27082440');
    Http::assertSentCount(1);

    Carbon::setTestNow(now()->addHours(25));
    $this->ares->find('27082440');
    Http::assertSentCount(2);

    Carbon::setTestNow();
});

it('can bypass the cache', function () {
    Http::fake([ARES_URL => Http::response(aresPayload())]);

    $this->ares->find('27082440');
    $this->ares->find('27082440', fresh: true);

    Http::assertSentCount(2);
});

it('rejects an invalid IČO without calling ARES', function (string $ico) {
    Http::fake();

    expect(fn () => $this->ares->find($ico))->toThrow(InvalidIcoException::class);

    Http::assertNothingSent();
})->with(['bad checksum' => '27082441', 'too short' => '2708244', 'letters' => 'ABCDEFGH', 'empty' => '']);

it('throws not found for an unknown subject and does not cache it', function () {
    Http::fake([ARES_URL => Http::response(['kod' => 'NENALEZENO', 'popis' => 'Záznam nenalezen'], 404)]);

    expect(fn () => $this->ares->find('27082440'))->toThrow(AresSubjectNotFoundException::class);
    expect(fn () => $this->ares->find('27082440'))->toThrow(AresSubjectNotFoundException::class);

    Http::assertSentCount(2);
});

it('maps a bad request from ARES to an invalid IČO', function () {
    Http::fake([ARES_URL => Http::response(['kod' => 'CHYBA_VSTUPU'], 400)]);

    $this->ares->find('27082440');
})->throws(InvalidIcoException::class);

it('retries once after a server error', function () {
    Http::fake([ARES_URL => Http::sequence()->push('', 500)->push(aresPayload())]);

    expect($this->ares->find('27082440')->name)->toBe('Alza.cz a.s.');

    Http::assertSentCount(2);
});

it('gives up after one retry and reports ARES as unavailable', function () {
    Http::fake([ARES_URL => Http::response('', 503)]);

    expect(fn () => $this->ares->find('27082440'))->toThrow(AresUnavailableException::class);

    Http::assertSentCount(2);
});

it('reports ARES as unavailable when the connection fails', function () {
    Http::fake([ARES_URL => fn () => throw new ConnectionException('timeout')]);

    expect(fn () => $this->ares->find('27082440'))->toThrow(AresUnavailableException::class);
});

it('reports ARES as unavailable for an unexpected response', function () {
    Http::fake([ARES_URL => Http::response(['neco' => 'jineho'])]);

    $this->ares->find('27082440');
})->throws(AresUnavailableException::class);

it('does not retry a missing subject', function () {
    Http::fake([ARES_URL => Http::response([], 404)]);

    try {
        $this->ares->find('27082440');
    } catch (AresSubjectNotFoundException) {
    }

    Http::assertSentCount(1);
});

describe('Contact::fillFromAres', function () {
    it('copies the data and stamps the sync time', function () {
        Carbon::setTestNow('2026-10-26 09:00:00');
        $contact = new Contact(['name' => 'Starý název']);
        $subject = new AresSubject('Alza.cz a.s.', '27082440', 'CZ27082440', 'Jankovcova 1522/53', 'Praha', '17000', 'CZ');

        $returned = $contact->fillFromAres($subject);

        expect($returned)->toBe($contact)
            ->and($contact->name)->toBe('Alza.cz a.s.')
            ->and($contact->ico)->toBe('27082440')
            ->and($contact->dic)->toBe('CZ27082440')
            ->and($contact->street)->toBe('Jankovcova 1522/53')
            ->and($contact->city)->toBe('Praha')
            ->and($contact->zip)->toBe('17000')
            ->and($contact->country)->toBe('CZ')
            ->and($contact->ares_synced_at->toDateTimeString())->toBe('2026-10-26 09:00:00');

        Carbon::setTestNow();
    });

    it('persists the synchronised contact', function () {
        $contact = Contact::factory()->create(['ares_synced_at' => null]);
        $subject = new AresSubject('Nová s.r.o.', '27082440', null, 'Ulice 1', 'Brno', '60200', 'CZ');

        $contact->fillFromAres($subject)->save();

        $fresh = $contact->fresh();
        expect($fresh->name)->toBe('Nová s.r.o.')->and($fresh->dic)->toBeNull()->and($fresh->ares_synced_at)->not->toBeNull();
    });
});
