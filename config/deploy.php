<?php

/*
|--------------------------------------------------------------------------
| Nasazení bez SSH
|--------------------------------------------------------------------------
|
| Pipeline (`.github/workflows/deploy.yml`) nahraje kód a značku `deploy.pending`.
| Minutový cron pak spustí `app:post-deploy`. Tokeny se čtou z `.env`;
| prázdný nebo příliš krátký token znamená, že daná funkce je vypnutá (404).
|
*/

return [
    // Značka „čeká se na dokončení nasazení“, obsahuje SHA commitu.
    'marker' => storage_path('app/deploy.pending'),

    // SHA právě nasazené verze (zapisuje app:post-deploy, čte healthcheck).
    'version_file' => storage_path('app/deployed-version'),

    // Zámek proti souběhu dvou spuštění cronu.
    'lock_file' => storage_path('framework/deploy.lock'),

    'health_token' => (string) env('HEALTH_TOKEN', ''),
    'cron_token' => (string) env('CRON_TOKEN', ''),
    'setup_token' => (string) env('SETUP_TOKEN', ''),

    // Minimální délka CRON_TOKEN a SETUP_TOKEN.
    'min_token_length' => 40,
];
