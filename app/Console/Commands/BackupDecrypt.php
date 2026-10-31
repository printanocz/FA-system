<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupCrypto;
use App\Domain\Backup\BackupException;
use Illuminate\Console\Command;

class BackupDecrypt extends Command
{
    protected $signature = 'app:backup:decrypt {soubor : Šifrovaná záloha (.zip.enc)} {cil : Kam uložit dešifrovaný ZIP}';

    protected $description = 'Dešifruje zálohu klíčem BACKUP_KEY z .env na obyčejný ZIP';

    public function handle(): int
    {
        $source = (string) $this->argument('soubor');
        $target = (string) $this->argument('cil');

        if (! is_file($source)) {
            $this->error("Soubor {$source} neexistuje.");

            return self::FAILURE;
        }

        try {
            BackupCrypto::fromConfig()->decrypt($source, $target);
        } catch (BackupException $e) {
            @unlink($target);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Hotovo: {$target}. Uvnitř je database.sql (import přes phpMyAdmin) a složka storage/private (nahrát do storage/app/private).");

        return self::SUCCESS;
    }
}
