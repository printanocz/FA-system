<?php

namespace App\Deploy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

/** Spouští Artisan příkazy nasazení a zapisuje jejich výstup do `deploy.log`. V testech se nahrazuje. */
class CommandRunner
{
    /** @param  array<string, mixed>  $parameters */
    public function run(string $command, array $parameters = []): int
    {
        $output = new BufferedOutput;
        $code = Artisan::call($command, $parameters, $output);

        Log::channel('deploy')->info("artisan {$command}", [
            'code' => $code,
            'output' => trim($output->fetch()),
        ]);

        return $code;
    }
}
