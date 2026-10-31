<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/nastaveni/dvoufazove-overeni')->assertRedirect('/login');
});

it('renders the login screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
});

it('logs in with the correct password and then forces 2FA setup', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    // Bez zapnutého 2FA se nikam jinam nedostane.
    $this->get('/')->assertRedirect(route('two-factor.setup'));
});

it('rejects a wrong password with a Czech message', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'Nesprávný e-mail nebo heslo.']);

    $this->assertGuest();
});

it('throttles login after 5 failed attempts per e-mail and IP', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'Nesprávný e-mail nebo heslo.']);
    }

    // Šestý pokus je zablokovaný, i kdyby heslo bylo správné.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    $message = session('errors')->getBag('default')->first('email');
    expect($message)->toStartWith('Příliš mnoho pokusů. Zkuste to znovu za ');
});

it('does not expose registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Test',
        'email' => 'test@example.test',
        'password' => 'a-long-enough-password',
        'password_confirmation' => 'a-long-enough-password',
    ])->assertNotFound();
});
