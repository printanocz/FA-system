<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\DashboardQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Přehled: karty, graf, limit DPH, faktury po splatnosti a poslední doklady na jeden požadavek. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardQuery $query): Response
    {
        return Inertia::render('Dashboard', $query->data(DashboardQuery::normalizePeriod($request->query('obdobi'))));
    }
}
