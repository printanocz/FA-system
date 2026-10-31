<?php

/*
|--------------------------------------------------------------------------
| Zálohy
|--------------------------------------------------------------------------
|
| Denně v 02:30 (scheduler): dump DB + archivní PDF (storage/app/private) → ZIP →
| zašifrováno libsodiem (secretstream) → storage/app/backups a, když je nastaven,
| off-site S3-kompatibilní disk `backup` (Backblaze B2). Postup obnovy: docs/BACKUP.md.
|
*/

return [
    // Base64 klíč o 32 bajtech: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    'key' => (string) env('BACKUP_KEY', ''),

    // Disk s lokální kopií (storage/app/backups) a off-site disk (S3-kompatibilní).
    'local_disk' => 'backups',
    'remote_disk' => 'backup',

    // Retence: poslední záloha každého z posledních 14 dní + poslední záloha každého měsíce 10 let.
    'keep_daily' => 14,
    'keep_monthly' => 120,

    // Po této době bez úspěšné zálohy se v aplikaci zobrazí banner.
    'stale_after_hours' => 48,

    // Cesta k adresáři s mysqldump (prázdné = hledá se v PATH). Bez exec/proc_open se použije čistě PHP dumper.
    'mysqldump_path' => (string) env('BACKUP_MYSQLDUMP_PATH', ''),
];
