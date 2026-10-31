<?php

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
use App\Rules\Ico;
use App\Services\Ares\AresClient;
use App\Services\Ares\Exceptions\AresException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** „Načíst z ARES“ ve formuláři kontaktu: vrací hodnoty pro předvyplnění, nic neukládá. */
class ContactAresController extends Controller
{
    public function __invoke(Request $request, AresClient $ares): JsonResponse
    {
        $ico = $request->validate(['ico' => ['required', 'string', 'max:20', new Ico]], [], ['ico' => 'IČO'])['ico'];

        try {
            $subject = $ares->find($ico);
        } catch (AresException $e) {
            throw ValidationException::withMessages(['ico' => $e->getMessage()]);
        }

        // DIČ jen když ho ARES vrátí; jinak klient ponechá to, co je ve formuláři.
        return response()->json(array_filter([
            'name' => $subject->name,
            'ico' => $subject->ico,
            'dic' => $subject->dic,
            'street' => $subject->street,
            'city' => $subject->city,
            'zip' => $subject->zip,
            'country' => $subject->country,
        ], static fn (?string $value): bool => $value !== null));
    }
}
