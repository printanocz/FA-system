<?php

namespace App\Domain\Backup;

use Druidfi\Mysqldump\DumpSettings;
use Druidfi\Mysqldump\Mysqldump;
use Illuminate\Support\Facades\DB;
use PDO;
use Spatie\DbDumper\Databases\MySql;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * SQL dump aktuální databáze do souboru.
 *
 * - MySQL/MariaDB: `mysqldump` přes spatie/db-dumper, když hosting povoluje exec a proc_open
 *   a binárka existuje; jinak čistě PHP dumper (druidfi/mysqldump-php).
 * - SQLite (testy a lokální vývoj): vlastní dump z PHP.
 */
class DatabaseDumper
{
    /** Znaková sada dumpu; mysqldump (spatie) má DROP TABLE ve výchozím nastavení (--opt). */
    public const CHARSET = 'utf8mb4';

    public function dump(string $path): void
    {
        $connection = DB::connection();
        /** @var array<string, mixed> $config */
        $config = $connection->getConfig();
        $driver = (string) $connection->getDriverName();

        try {
            match ($driver) {
                'sqlite' => $this->dumpSqlite($connection->getPdo(), $path),
                'mysql', 'mariadb' => $this->dumpMysql($config, $path),
                default => throw new BackupException("Databáze typu {$driver} není pro zálohu podporovaná."),
            };
        } catch (BackupException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new BackupException('Dump databáze selhal: '.$e->getMessage(), previous: $e);
        }

        if (! is_file($path) || filesize($path) === 0) {
            throw new BackupException('Dump databáze je prázdný.');
        }
    }

    /** Je k dispozici nativní mysqldump (povolené exec a proc_open, binárka nalezena)? */
    public function mysqldumpDirectory(): ?string
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        foreach (['exec', 'proc_open'] as $function) {
            if (! function_exists($function) || in_array($function, $disabled, true)) {
                return null;
            }
        }

        $configured = (string) config('backup.mysqldump_path');
        $binary = (new ExecutableFinder)->find('mysqldump', null, $configured !== '' ? [$configured] : []);

        return $binary === null ? null : dirname($binary);
    }

    /**
     * Nastavení čistě PHP dumperu (druidfi): utf8mb4, DROP TABLE před každým CREATE, konzistentní snímek.
     *
     * @return array<string, mixed>
     */
    public function phpDumperSettings(): array
    {
        return [
            'single-transaction' => true,
            'default-character-set' => DumpSettings::UTF8MB4,
            'add-drop-table' => true,
        ];
    }

    /** @param  array<string, mixed>  $config */
    private function dumpMysql(array $config, string $path): void
    {
        $directory = $this->mysqldumpDirectory();

        if ($directory !== null) {
            MySql::create()
                ->setDbName((string) $config['database'])
                ->setUserName((string) ($config['username'] ?? ''))
                ->setPassword((string) ($config['password'] ?? ''))
                ->setHost((string) ($config['host'] ?? '127.0.0.1'))
                ->setPort((int) ($config['port'] ?? 3306))
                ->setDumpBinaryPath($directory)
                ->setDefaultCharacterSet(self::CHARSET)
                ->useSingleTransaction()
                ->dumpToFile($path);

            return;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'] ?? '127.0.0.1', (int) ($config['port'] ?? 3306), $config['database']);
        (new Mysqldump($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), $this->phpDumperSettings()))->start($path);
    }

    private function dumpSqlite(PDO $pdo, string $path): void
    {
        $out = fopen($path, 'wb');
        if ($out === false) {
            throw new BackupException("Soubor {$path} nejde otevřít.");
        }

        try {
            fwrite($out, "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");

            $objects = $pdo->query("select type, name, tbl_name, sql from sqlite_master where sql is not null and name not like 'sqlite_%' order by case type when 'table' then 0 else 1 end, rowid");
            $rows = $objects === false ? [] : $objects->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if ($row['type'] === 'table') {
                    fwrite($out, $row['sql'].";\n");
                    $this->dumpTableRows($pdo, $out, (string) $row['name']);
                }
            }
            foreach ($rows as $row) {
                if ($row['type'] !== 'table') {
                    fwrite($out, $row['sql'].";\n");
                }
            }

            fwrite($out, "COMMIT;\n");
        } finally {
            fclose($out);
        }
    }

    /** @param  resource  $out */
    private function dumpTableRows(PDO $pdo, $out, string $table): void
    {
        $quoted = '"'.str_replace('"', '""', $table).'"';
        $statement = $pdo->query("select * from {$quoted}");
        if ($statement === false) {
            return;
        }

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $values = array_map(fn (mixed $value): string => match (true) {
                $value === null => 'NULL',
                is_int($value) => (string) $value,
                is_float($value) => var_export($value, true),
                default => "'".str_replace("'", "''", (string) $value)."'",
            }, array_values($row));

            fwrite($out, "INSERT INTO {$quoted} VALUES(".implode(',', $values).");\n");
        }
    }
}
