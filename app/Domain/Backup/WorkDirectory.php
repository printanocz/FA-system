<?php

namespace App\Domain\Backup;

/**
 * Pracovní složka pro zálohy a export: storage/app/backup-work (0700), mimo disky `local` i `backups`.
 * Na sdíleném hostingu může open_basedir blokovat /tmp a nešifrovaný dump tam nemá co dělat.
 */
final class WorkDirectory
{
    public static function root(): string
    {
        return storage_path('app/backup-work');
    }

    /** Vytvoří novou unikátní podsložku (0700) a vrátí její cestu. */
    public static function create(string $prefix): string
    {
        $root = self::root();
        if (! is_dir($root) && ! @mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new BackupException('Pracovní složku nejde vytvořit.');
        }
        @chmod($root, 0700);

        $path = $root.'/'.$prefix.'-'.bin2hex(random_bytes(6));
        if (! mkdir($path, 0700) && ! is_dir($path)) {
            throw new BackupException('Pracovní složku nejde vytvořit.');
        }

        return $path;
    }

    /** Vytvoří prázdný dočasný soubor v pracovní složce a vrátí jeho cestu. */
    public static function tempFile(string $prefix): string
    {
        $root = self::root();
        if (! is_dir($root) && ! @mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new BackupException('Pracovní složku nejde vytvořit.');
        }
        @chmod($root, 0700);

        $path = tempnam($root, $prefix.'-');
        if ($path === false) {
            throw new BackupException('Dočasný soubor nejde vytvořit.');
        }
        @chmod($path, 0600);

        return $path;
    }
}
