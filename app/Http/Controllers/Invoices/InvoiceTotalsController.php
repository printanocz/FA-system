<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Documents\SaveInvoiceDraft;
use App\Domain\Documents\CalculatorLine;
use App\Domain\Documents\DocumentCalculator;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Součty editoru z DocumentCalculator, jediný zdroj pravdy. Nic neukládá. */
class InvoiceTotalsController extends Controller
{
    public function __invoke(Request $request, DocumentCalculator $calculator): JsonResponse
    {
        $input = SaveInvoiceDraft::normalize($request->all());

        $data = Validator::make($input, [
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'round_total' => ['required', 'boolean'],
            'items' => ['array', 'max:100'],
            'items.*.qty' => ['required', 'numeric', 'min:0.0001', 'max:9999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:-9999999999999', 'max:9999999999999'],
        ], SaveInvoiceDraft::messages())->validate();

        $totals = $calculator->calculate(
            lines: array_map(
                fn (array $item): CalculatorLine => new CalculatorLine($item['qty'], $item['unit_price']),
                $data['items'] ?? [],
            ),
            discountPercent: $data['discount_percent'],
            roundTotal: (bool) $data['round_total'],
            vatPayer: (bool) CompanyProfile::current()?->vat_payer,
        );

        return response()->json([
            'lines' => array_map(fn ($line): string => (string) $line, $totals->lineTotals),
            'subtotal' => (string) $totals->subtotal,
            'discount_total' => (string) $totals->discountTotal,
            'vat_total' => (string) $totals->vatTotal,
            'rounding' => (string) $totals->rounding,
            'total' => (string) $totals->total,
        ]);
    }
}
