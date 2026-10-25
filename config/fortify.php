<?php

use Laravel\Fortify\Features;

return [

    /*
    | Fortify běží headless: stránky vykresluje Inertia (viz FortifyServiceProvider).
    | Registrace není zapnutá (Features::registration() chybí), proto route /register
    | neexistuje. Uživatele zakládá jen příkaz `php artisan app:create-user`.
    */

    'guard' => 'web',

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'paths' => [
        'login' => null,
        'logout' => null,
        'password' => [
            'request' => null,
            'reset' => null,
            'email' => null,
            'update' => null,
            'confirm' => null,
            'confirmation' => null,
        ],
        'register' => null,
        'verification' => [
            'notice' => null,
            'verify' => null,
            'send' => null,
        ],
        'user-profile-information' => null,
        'user-password' => null,
        'two-factor' => [
            'login' => null,
            'enable' => null,
            'confirm' => null,
            'disable' => null,
            'qr-code' => null,
            'secret-key' => null,
            'recovery-codes' => null,
        ],
    ],

    'redirects' => [
        'login' => null,
        'logout' => null,
        'password-confirmation' => null,
        'register' => null,
        'email-verification' => null,
        'password-reset' => null,
    ],

    /*
    | Přihlášení: vestavěný limiter Fortify (5 neúspěšných pokusů za minutu na
    | e-mail + IP), odpověď je validační chyba u pole e-mail.
    | 2FA výzva: pojmenovaný limiter `two-factor` (definován v FortifyServiceProvider).
    */
    'limiters' => [
        'login' => null,
        'two-factor' => 'two-factor',
    ],

    'views' => true,

    'features' => [
        Features::resetPasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],

];
