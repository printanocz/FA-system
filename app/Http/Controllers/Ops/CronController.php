<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * Záložní cron pro hosting, který umí jen volat URL: `GET /_cron/{token}` spustí `schedule:run`.
 * Token je z `.env` (CRON_TOKEN, nejméně 40 znaků), jinak 404.
 */
class CronController extends Controller
{
    public function __invoke(string $token): Response
    {
        $expected = (string) config('deploy.cron_token');

        abort_if(
            strlen($expected) < (int) config('deploy.min_token_length') || ! hash_equals($expected, $token),
            404,
        );

        Artisan::call('schedule:run');

        return response('ok', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
