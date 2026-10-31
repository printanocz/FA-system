<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupService;
use App\Models\Backup;
use Illuminate\Console\Command;

class BackupRun extends Command
{
    protected $signature = 'app:backup:run';

    protected $description = 'Zálohuje databázi a archivní PDF (šifrovaně, lokálně a off-site)';

    public function handle(BackupService $service): int
    {
        $backup = $service->run();

        if ($backup->status !== Backup::STATUS_OK) {
            $this->error('Záloha selhala: '.$backup->error);

            return self::FAILURE;
        }

        $this->info("Záloha {$backup->filename} hotová (".number_format($backup->size / 1048576, 2, ',', ' ').' MB, '
            .($backup->stored_remote ? 'lokálně i off-site' : 'jen lokálně').').');
        if ($backup->error !== null) {
            $this->warn($backup->error);
        }

        return self::SUCCESS;
    }
}
