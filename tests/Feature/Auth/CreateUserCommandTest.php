<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a user from interactive input', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('E-mail', 'Adam@Example.test')
        ->expectsQuestion('Jméno', 'Adam')
        ->expectsQuestion('Heslo (min. 12 znaků)', 'correct horse battery')
        ->expectsQuestion('Heslo znovu', 'correct horse battery')
        ->assertSuccessful();

    $user = User::query()->where('email', 'adam@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Adam')
        ->and(Hash::check('correct horse battery', $user->password))->toBeTrue()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('refuses a short password and creates nothing', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('E-mail', 'adam@example.test')
        ->expectsQuestion('Jméno', 'Adam')
        ->expectsQuestion('Heslo (min. 12 znaků)', 'short')
        ->expectsQuestion('Heslo znovu', 'short')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses a duplicate e-mail', function () {
    User::factory()->create(['email' => 'adam@example.test']);

    $this->artisan('app:create-user')
        ->expectsQuestion('E-mail', 'adam@example.test')
        ->expectsQuestion('Jméno', 'Adam')
        ->expectsQuestion('Heslo (min. 12 znaků)', 'correct horse battery')
        ->expectsQuestion('Heslo znovu', 'correct horse battery')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});
