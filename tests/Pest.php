<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
