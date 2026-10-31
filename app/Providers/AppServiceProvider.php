<?php

namespace App\Providers;

use App\Domain\Backup\RetentionPolicy;
use App\Domain\Documents\DocumentType;
use App\Models\Document;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RetentionPolicy::class, fn (): RetentionPolicy => RetentionPolicy::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Adresy /faktury/{invoice} ukážou jen faktury; nabídka, záloha nebo dobropis dá 404.
        Route::bind('invoice', fn (string $value): Document => Document::query()
            ->where('type', DocumentType::Invoice)
            ->findOrFail($value));
    }
}
