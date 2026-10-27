<?php

use App\Http\Controllers\Contacts\ContactAresController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Settings\AresController;
use App\Http\Controllers\Settings\BankAccountController;
use App\Http\Controllers\Settings\NumberSeriesController;
use App\Http\Controllers\Settings\RecoveryCodesController;
use App\Http\Controllers\Settings\SettingsController;
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

    Route::prefix('adresar')->name('contacts.')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->name('index');
        Route::post('/', [ContactController::class, 'store'])->name('store');
        Route::post('ares', ContactAresController::class)->name('ares');
        Route::get('{contact}', [ContactController::class, 'index'])->name('show')->whereNumber('contact');
        Route::put('{contact}', [ContactController::class, 'update'])->name('update')->whereNumber('contact');
        Route::delete('{contact}', [ContactController::class, 'destroy'])->name('destroy')->whereNumber('contact');
        Route::post('{contact}/obnovit', [ContactController::class, 'restore'])->name('restore')->whereNumber('contact');
        Route::post('{contact}/ares', [ContactController::class, 'refreshFromAres'])->name('refresh-ares')->whereNumber('contact');
    });

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
