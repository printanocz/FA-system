<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateCompanyProfile;
use App\Http\Controllers\Controller;
use App\Services\Ares\Exceptions\AresException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** „Načíst z ARES“ ve formuláři firmy: vrací hodnoty pro předvyplnění, nic neukládá. */
class AresController extends Controller
{
    public function __invoke(Request $request, UpdateCompanyProfile $company): JsonResponse
    {
        $ico = $request->validate(['ico' => ['required', 'string', 'max:20']], [], ['ico' => 'IČO'])['ico'];

        try {
            return response()->json($company->suggestFromAres($ico));
        } catch (AresException $e) {
            throw ValidationException::withMessages(['ico' => $e->getMessage()]);
        }
    }
}
