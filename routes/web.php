<?php

use App\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Přihlášení, reset hesla a 2FA endpointy registruje Fortify (config/fortify.php).

// Průvodce nastavením 2FA: stačí přihlášení (jinak by se nikdo nedostal dál).
Route::get('/nastaveni/dvoufazove-overeni', [TwoFactorSetupController::class, 'show'])
    ->middleware(['auth', 'password.confirm'])
    ->name('two-factor.setup');

// Všechno ostatní jen pro přihlášené s potvrzeným 2FA.
Route::middleware(['auth', 'two-factor.required'])->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
});
