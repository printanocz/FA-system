<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateNumberSeries;
use App\Domain\Documents\Exceptions\NumberSeriesException;
use App\Http\Controllers\Controller;
use App\Models\NumberSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NumberSeriesController extends Controller
{
    public function update(Request $request, NumberSeries $numberSeries, UpdateNumberSeries $update): RedirectResponse
    {
        try {
            $update($numberSeries, $request->only(['name', 'prefix', 'format', 'next_number', 'reset_yearly']));
        } catch (NumberSeriesException $e) {
            // Snížení čísla pod už přidělené: chyba u pole „další číslo“.
            throw ValidationException::withMessages(['next_number' => $e->getMessage()]);
        }

        return back();
    }
}
