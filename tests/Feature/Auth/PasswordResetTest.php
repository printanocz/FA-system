<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a reset link by e-mail', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('answers an unknown e-mail the same way and sends nothing', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'nobody@example.test'])
        ->assertSessionHas('status', __('passwords.sent'))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('sets a new password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('login'));

    expect(Hash::check('a-brand-new-password', $user->refresh()->password))->toBeTrue();
});

it('rejects a too short new password', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
