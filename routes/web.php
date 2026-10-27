<?php

use App\Http\Controllers\Settings\AresController;
use App\Http\Controllers\Settings\BankAccountController;
use App\Http\Controllers\Settings\NumberSeriesController;
use App\Http\Controllers\Settings\RecoveryCodesController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Přihlášení, reset hesla a 2FA endpointy registruje Fortify (config/fortify.php).

// Založení prvního uživatele bez SSH: existuje jen s SETUP_TOKEN a prázdnou tabulkou uživatelů.
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/setup', [SetupController::class, 'show']);
    Route::post('/setup', [SetupController::class, 'store']);
});

// Průvodce nastavením 2FA: stačí přihlášení (jinak by se nikdo nedostal dál).
Route::get('/nastaveni/dvoufazove-overeni', [TwoFactorSetupController::class, 'show'])
    ->middleware(['auth', 'password.confirm'])
    ->name('two-factor.setup');

// Všechno ostatní jen pro přihlášené s potvrzeným 2FA.
Route::middleware(['auth', 'two-factor.required'])->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::prefix('nastaveni')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'show'])->name('show');
        Route::put('/', [SettingsController::class, 'update'])->name('update');
        Route::post('ares', AresController::class)->name('ares');

        Route::post('ucty', [BankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::put('ucty/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
        Route::delete('ucty/{bankAccount}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

        Route::put('rady/{numberSeries}', [NumberSeriesController::class, 'update'])->name('number-series.update');

        Route::get('zalozni-kody', RecoveryCodesController::class)
            ->middleware('password.confirm')
            ->name('recovery-codes');
    });
});
