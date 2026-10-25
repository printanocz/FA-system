<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('sends a user with 2FA to the challenge after the password step', function () {
    [$user] = twoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('renders the challenge screen only during a pending login', function () {
    [$user] = twoFactorUser();

    $this->get('/two-factor-challenge')->assertRedirect('/login');

    $this->withSession(['login.id' => $user->id])
        ->get('/two-factor-challenge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));
});

it('completes login with a valid TOTP code', function () {
    [$user, $secret] = twoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post('/two-factor-challenge', ['code' => totpCode($secret)])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    $this->get('/')->assertOk();
});

it('rejects an invalid TOTP code', function () {
    [$user] = twoFactorUser();

    $this->withSession(['login.id' => $user->id])
        ->post('/two-factor-challenge', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => 'Ověřovací kód není správný.']);

    $this->assertGuest();
});

it('completes login with a recovery code', function () {
    [$user] = twoFactorUser();

    $this->withSession(['login.id' => $user->id])
        ->post('/two-factor-challenge', ['recovery_code' => 'recovery-one'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('throttles the 2FA challenge after 5 attempts per minute', function () {
    [$user] = twoFactorUser();

    foreach (range(1, 5) as $attempt) {
        $this->withSession(['login.id' => $user->id])
            ->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'Ověřovací kód není správný.']);
    }

    $this->withSession(['login.id' => $user->id])
        ->post('/two-factor-challenge', ['code' => '000000'])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHasErrors('code');

    $message = session('errors')->getBag('default')->first('code');
    expect($message)->toStartWith('Příliš mnoho pokusů. Zkuste to znovu za ');
});

it('redirects a user without confirmed 2FA to the setup page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('two-factor.setup'));

    // Rozpracované (nepotvrzené) 2FA nestačí.
    $user->forceFill(['two_factor_secret' => encrypt('SECRET')])->save();
    $this->actingAs($user)->get('/')->assertRedirect(route('two-factor.setup'));
});

it('asks for the password before the 2FA setup page and before changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('two-factor.setup'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($user)
        ->post('/user/two-factor-authentication')
        ->assertRedirect(route('password.confirm'));

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

it('confirms the password with a Czech error for a wrong one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/user/confirm-password', ['password' => 'wrong-password'])
        ->assertSessionHasErrors(['password' => 'Zadané heslo není správné.']);

    $this->actingAs($user)
        ->post('/user/confirm-password', ['password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('two-factor.setup'))
        ->assertOk();
});

it('walks through enabling 2FA: start, QR code, confirmation, recovery codes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('two-factor.setup'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/TwoFactorSetup')
            ->where('step', 'start')
            ->where('qrCodeSvg', null));

    $this->post('/user/two-factor-authentication');

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $this->get(route('two-factor.setup'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('step', 'confirm')
            ->where('secretKey', decrypt($user->two_factor_secret))
            ->has('qrCodeSvg'));

    $this->post('/user/confirmed-two-factor-authentication', ['code' => '000000'])
        ->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');
    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();

    $this->post('/user/confirmed-two-factor-authentication', [
        'code' => totpCode(decrypt($user->two_factor_secret)),
    ]);
    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->get(route('two-factor.setup'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('step', 'codes')
            ->has('recoveryCodes', 8));

    // Po potvrzení už aplikace pustí dál.
    $this->get('/')->assertOk();
});
