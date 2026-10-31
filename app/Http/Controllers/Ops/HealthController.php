<?php

namespace App\Http\Controllers\Ops;

use App\Domain\Backup\BackupStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Healthcheck pro pipeline: bez správné hlavičky `X-Health-Token` se tváří, že neexistuje. */
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('deploy.health_token');
        $given = (string) $request->header('X-Health-Token', '');

        abort_if($expected === '' || ! hash_equals($expected, $given), 404);

        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'fail';
        }

        $versionFile = (string) config('deploy.version_file');
        $version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';

        return response()->json([
            'app' => 'ok',
            'db' => $database,
            'version' => $version === '' ? 'unknown' : $version,
            'maintenance' => app()->isDownForMaintenance(),
            'backup_age_hours' => $database === 'ok' ? BackupStatus::ageHours() : null,
        ]);
    }
}
