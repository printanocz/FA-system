<?php

use App\Http\Controllers\Ops\CronController;
use App\Http\Controllers\Ops\HealthController;
use Illuminate\Support\Facades\Route;

// Provozní adresy bez session, cookies a CSRF. Mimo údržbový režim (bootstrap/app.php).
// Omezení počtu volání brání hádání tokenu.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/_health', HealthController::class);
    Route::get('/_cron/{token}', CronController::class);
});
