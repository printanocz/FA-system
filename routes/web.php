<?php

use App\Http\Controllers\Contacts\ContactAresController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\ContactLookupController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoiceEmailController;
use App\Http\Controllers\Invoices\InvoiceListController;
use App\Http\Controllers\Invoices\InvoicePaymentController;
use App\Http\Controllers\Invoices\InvoicePdfController;
use App\Http\Controllers\Invoices\InvoiceTotalsController;
use App\Http\Controllers\PriceItems\PriceItemController;
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

    Route::prefix('faktury')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceListController::class, 'index'])->name('index');
        Route::get('nova', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::post('vystavit', [InvoiceController::class, 'issueNew'])->name('issue-new');
        Route::post('prepocet', InvoiceTotalsController::class)->name('totals');
        Route::post('hromadne-uhrazeni', [InvoiceListController::class, 'pay'])->name('pay-many');
        Route::get('{invoice}', [InvoiceController::class, 'show'])->name('show')->whereNumber('invoice');
        Route::get('{invoice}/upravit', [InvoiceController::class, 'edit'])->name('edit')->whereNumber('invoice');
        Route::put('{invoice}', [InvoiceController::class, 'update'])->name('update')->whereNumber('invoice');
        Route::post('{invoice}/vystavit', [InvoiceController::class, 'issue'])->name('issue')->whereNumber('invoice');
        Route::delete('{invoice}', [InvoiceController::class, 'destroy'])->name('destroy')->whereNumber('invoice');
        Route::get('{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('pdf')->whereNumber('invoice');
        Route::post('{invoice}/odeslat', [InvoiceEmailController::class, 'store'])->name('email')->whereNumber('invoice');
        Route::post('{invoice}/kopie', [InvoiceController::class, 'copy'])->name('copy')->whereNumber('invoice');
        Route::post('{invoice}/uhrady', [InvoicePaymentController::class, 'store'])->name('payments.store')->whereNumber('invoice');
        Route::delete('{invoice}/uhrady/{payment}', [InvoicePaymentController::class, 'destroy'])->name('payments.destroy')
            ->whereNumber(['invoice', 'payment'])->scopeBindings();
    });

    Route::prefix('adresar')->name('contacts.')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->name('index');
        Route::post('/', [ContactController::class, 'store'])->name('store');
        Route::post('ares', ContactAresController::class)->name('ares');
        Route::get('hledat', [ContactLookupController::class, 'search'])->name('search');
        Route::post('z-ares', [ContactLookupController::class, 'createFromAres'])->name('create-from-ares');
        Route::get('{contact}', [ContactController::class, 'index'])->name('show')->whereNumber('contact');
        Route::put('{contact}', [ContactController::class, 'update'])->name('update')->whereNumber('contact');
        Route::delete('{contact}', [ContactController::class, 'destroy'])->name('destroy')->whereNumber('contact');
        Route::post('{contact}/obnovit', [ContactController::class, 'restore'])->name('restore')->whereNumber('contact');
        Route::post('{contact}/ares', [ContactController::class, 'refreshFromAres'])->name('refresh-ares')->whereNumber('contact');
    });

    Route::prefix('cenik')->name('price-items.')->group(function () {
        Route::get('/', [PriceItemController::class, 'index'])->name('index');
        Route::post('/', [PriceItemController::class, 'store'])->name('store');
        Route::get('hledat', [PriceItemController::class, 'search'])->name('search');
        Route::put('{priceItem}', [PriceItemController::class, 'update'])->name('update')->whereNumber('priceItem');
        Route::delete('{priceItem}', [PriceItemController::class, 'destroy'])->name('destroy')->whereNumber('priceItem');
        Route::post('{priceItem}/obnovit', [PriceItemController::class, 'restore'])->name('restore')->whereNumber('priceItem');
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
