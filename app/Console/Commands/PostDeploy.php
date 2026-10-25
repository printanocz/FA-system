<?php

namespace App\Console\Commands;

use App\Deploy\CommandRunner;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Dokončí nasazení, které CI nahrálo přes FTP: pokud existuje značka `deploy.pending`,
 * spustí migrace, seeder, cache a vypne údržbový režim. Spouští ho scheduler každou minutu.
 */
class PostDeploy extends Command
{
    protected $signature = 'app:post-deploy';

    protected $description = 'Po nahrání nové verze provede migrace, cache a vypne údržbový režim';

    public function handle(CommandRunner $runner): int
    {
        $marker = (string) config('deploy.marker');

        if (! is_file($marker)) {
            return self::SUCCESS;
        }

        // Dvě souběžná spuštění by spustila migrace dvakrát.
        $lock = fopen((string) config('deploy.lock_file'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS;
        }

        $sha = trim((string) file_get_contents($marker));
        $sha = $sha === '' ? 'unknown' : $sha;
        $started = microtime(true);
        $log = Log::channel('deploy');
        $log->info('Nasazení začalo', ['sha' => $sha]);

        try {
            $steps = [
                ['migrate', ['--force' => true]],
                ['db:seed', ['--class' => InitialSetupSeeder::class, '--force' => true]],
                ['optimize', []],
                ['view:cache', []],
                ['up', []],
            ];

            foreach ($steps as [$command, $parameters]) {
                $code = $runner->run($command, $parameters);

                if ($code !== self::SUCCESS) {
                    throw new RuntimeException("Příkaz {$command} skončil kódem {$code}.");
                }
            }

            file_put_contents((string) config('deploy.version_file'), $sha.PHP_EOL);
            unlink($marker);
        } catch (Throwable $e) {
            $log->error('Nasazení selhalo, aplikace zůstává v údržbě a značka zůstává pro další pokus', [
                'sha' => $sha,
                'error' => $e->getMessage(),
                'seconds' => round(microtime(true) - $started, 2),
            ]);
            $this->error($e->getMessage());

            if (! app()->isDownForMaintenance()) {
                $runner->run('down', ['--retry' => 60]);
            }

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $log->info('Nasazení dokončeno', ['sha' => $sha, 'seconds' => round(microtime(true) - $started, 2)]);

        return self::SUCCESS;
    }
}
