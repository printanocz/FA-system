<?php

namespace App\Domain\Backup;

use App\Models\Backup;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Jedna záloha: dump DB + archiv storage/app/private (archivní PDF) → ZIP → šifrování →
 * lokální kopie a (když je nastavené) off-site úložiště → retence.
 */
class BackupService
{
    public function __construct(
        private readonly DatabaseDumper $dumper,
        private readonly RetentionPolicy $retention,
    ) {}

    /** Je nastavené off-site úložiště (všechny S3 údaje)? */
    public static function remoteConfigured(): bool
    {
        foreach (['key', 'secret', 'bucket', 'endpoint'] as $field) {
            if ((string) config("filesystems.disks.backup.{$field}") === '') {
                return false;
            }
        }

        return true;
    }

    /** Provede zálohu a zapíše ji do tabulky `backups` (i neúspěšnou). */
    public function run(): Backup
    {
        try {
            return $this->create();
        } catch (Throwable $e) {
            Log::error('Záloha selhala', ['exception' => $e]);

            return Backup::query()->create([
                'status' => Backup::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }
    }

    private function create(): Backup
    {
        $crypto = BackupCrypto::fromConfig();
        @set_time_limit(0);

        $now = Carbon::now();
        $filename = RetentionPolicy::filename($now);
        $work = WorkDirectory::create('backup');

        try {
            $sql = $work.'/database.sql';
            $zip = $work.'/backup.zip';
            $encrypted = $work.'/'.$filename;

            $this->dumper->dump($sql);
            $this->zip($zip, $sql, $now);
            $crypto->encrypt($zip, $encrypted);

            $local = Storage::disk((string) config('backup.local_disk'));
            $local->putFileAs('', new File($encrypted), $filename);

            $remoteError = null;
            $remoteStored = false;
            if (self::remoteConfigured()) {
                try {
                    Storage::disk((string) config('backup.remote_disk'))->putFileAs('', new File($encrypted), $filename);
                    $remoteStored = true;
                } catch (Throwable $e) {
                    $remoteError = 'Off-site úložiště: '.$e->getMessage();
                    Log::error('Off-site záloha selhala', ['exception' => $e]);
                }
            }

            $this->prune($local, $now);
            if ($remoteStored) {
                $this->prune(Storage::disk((string) config('backup.remote_disk')), $now);
            }

            return Backup::query()->create([
                'filename' => $filename,
                'size' => (int) filesize($encrypted),
                'sha256' => hash_file('sha256', $encrypted) ?: null,
                'status' => Backup::STATUS_OK,
                'stored_local' => true,
                'stored_remote' => $remoteStored,
                'error' => $remoteError === null ? null : mb_substr($remoteError, 0, 2000),
            ]);
        } finally {
            $this->removeDirectory($work);
        }
    }

    private function zip(string $zipPath, string $sqlPath, Carbon $now): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new BackupException('Na serveru chybí PHP rozšíření zip.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('ZIP archiv zálohy nejde vytvořit.');
        }

        $disk = Storage::disk('local');
        $files = $disk->allFiles();
        $zip->addFile($sqlPath, 'database.sql');
        $zip->addFromString('MANIFEST.json', (string) json_encode([
            'created_at' => $now->toIso8601String(),
            'app' => (string) config('app.name'),
            'database' => (string) config('database.default'),
            'files' => count($files),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        foreach ($files as $file) {
            $zip->addFile($disk->path($file), 'storage/private/'.$file);
        }

        if (! $zip->close()) {
            throw new BackupException('ZIP archiv zálohy se nepodařilo dokončit.');
        }
    }

    /** Smaže zálohy mimo retenci na daném disku. */
    public function prune(Filesystem $disk, ?Carbon $now = null): int
    {
        $names = array_map(fn (string $path): string => basename($path), $disk->files());
        $expired = $this->retention->expired(array_values($names), $now ?? Carbon::now());

        foreach ($expired as $name) {
            $disk->delete($name);
        }

        return count($expired);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}
