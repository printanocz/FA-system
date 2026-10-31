<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dokončení nasazení (značka deploy.pending). Musí běžet i v údržbovém režimu, jinak by nikdy neskončil.
Schedule::command('app:post-deploy')->everyMinute()->evenInMaintenanceMode();

// Denní záloha (dump DB + archivní PDF, šifrovaně). Hlídá ji banner v aplikaci a /_health.
Schedule::command('app:backup:run')->dailyAt('02:30')->withoutOverlapping();
