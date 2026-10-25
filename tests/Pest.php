<?php

use App\Actions\Documents\IssueDocument;
use App\Domain\Documents\DocumentType;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\NumberSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the Laravel application. Vite is disabled so tests
| do not depend on a built frontend (CI runs Pest before `npm run build`).
|
*/

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

/**
 * Uživatel s potvrzeným 2FA. Vrací i tajný klíč, aby šel vygenerovat platný kód.
 *
 * @return array{0: User, 1: string}
 */
function twoFactorUser(): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

/** Aktuální platný TOTP kód pro daný tajný klíč. */
function totpCode(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

/**
 * Připraví vše pro vystavení faktury (firma, účet, řada) a vrátí koncept s položkami.
 *
 * @param  list<array{0: string|int, 1: string|int}>  $items  [množství, cena]
 * @param  array<string, mixed>  $attributes
 */
function draftInvoice(array $items = [[1, 1000]], array $attributes = []): Document
{
    CompanyProfile::current() ?? CompanyProfile::factory()->create();
    BankAccount::default() ?? BankAccount::factory()->default()->create();
    NumberSeries::defaultFor(DocumentType::Invoice)
        ?? NumberSeries::factory()->create();

    $document = Document::factory()->create($attributes);

    foreach ($items as $position => [$qty, $price]) {
        DocumentItem::factory()->create([
            'document_id' => $document->id,
            'position' => $position + 1,
            'qty' => (string) $qty,
            'unit_price' => (string) $price,
        ]);
    }

    return $document->fresh();
}

function issueInvoice(Document $document, ?string $date = null): Document
{
    return app(IssueDocument::class)(
        $document,
        $date === null ? null : Carbon::parse($date),
    );
}
