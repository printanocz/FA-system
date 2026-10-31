<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Backup\BackupException;
use App\Domain\Backup\BackupService;
use App\Domain\Backup\DataExport;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Nastavení › Záloha dat: ruční záloha a export dat ke stažení. */
class BackupController extends Controller
{
    /** „Zálohovat teď“: stejná záloha jako ve scheduleru, synchronně. */
    public function run(BackupService $service): RedirectResponse
    {
        $backup = $service->run();

        if ($backup->status !== Backup::STATUS_OK) {
            return back()->with('error', ['message' => 'Zálohu se nepodařilo vytvořit: '.$backup->error]);
        }

        return back()->with('status', $backup->stored_remote ? 'Záloha hotová a nahraná off-site' : 'Záloha hotová (jen lokálně)');
    }

    /** „Exportovat data“: nešifrovaný ZIP (PDF faktur + CSV) se vytvoří za běhu a po stažení smaže. */
    public function export(DataExport $export): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $export->build();
        } catch (BackupException $e) {
            return back()->with('error', ['message' => $e->getMessage()]);
        }

        return response()->download($path, $export->filename(), ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
