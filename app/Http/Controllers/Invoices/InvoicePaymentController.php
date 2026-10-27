<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Documents\DeletePayment;
use App\Actions\Documents\RecordPayment;
use App\Domain\Documents\Exceptions\DocumentException;
use App\Domain\Documents\Exceptions\InvalidDocumentStateException;
use App\Domain\Documents\Exceptions\OverpaymentException;
use App\Domain\Documents\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Ruční úhrady faktury: přidání (plná i částečná) a smazání poslední úhrady. */
class InvoicePaymentController extends Controller
{
    public function store(Request $request, Document $invoice, RecordPayment $record): RedirectResponse
    {
        $data = Validator::make($this->normalize($request->all()), [
            'amount' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'gt:0'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases()))],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Zadejte částku úhrady.',
            'amount.regex' => 'Částka musí být číslo s nejvýše dvěma desetinnými místy, například 3 200,00.',
            'amount.gt' => 'Částka musí být větší než nula.',
            'paid_at.required' => 'Zadejte datum úhrady.',
            'paid_at.date_format' => 'Zadejte datum ve tvaru 25. 10. 2026.',
            'paid_at.before_or_equal' => 'Datum úhrady nemůže být v budoucnu.',
            'method.required' => 'Vyberte způsob úhrady.',
            'method.in' => 'Vyberte způsob úhrady.',
            'note.max' => 'Poznámka může mít nejvýše 255 znaků.',
        ])->validate();

        try {
            $record($invoice, $data['amount'], $data['paid_at'], PaymentMethod::from($data['method']), $data['note'] ?? null);
        } catch (OverpaymentException) {
            throw ValidationException::withMessages([
                'amount' => 'Částka přesahuje zbývající '.self::money((string) $invoice->fresh()?->remainingAmount()).'. Přeplatek není povolený.',
            ]);
        } catch (InvalidDocumentStateException $e) {
            return back()->with('error', ['message' => $e->getMessage()]);
        }

        return back()->with('status', 'Úhrada zaznamenána');
    }

    public function destroy(Document $invoice, Payment $payment, DeletePayment $delete): RedirectResponse
    {
        try {
            $delete($payment);
        } catch (DocumentException $e) {
            return back()->with('error', ['message' => $e->getMessage()]);
        }

        return back()->with('status', 'Úhrada smazána');
    }

    /**
     * Částka v české podobě (čárka, mezery) na tečku, prázdné texty na null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(array $input): array
    {
        $text = static function (mixed $value): ?string {
            if (! is_string($value) && ! is_numeric($value)) {
                return null;
            }
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        $amount = $text($input['amount'] ?? null);

        return [
            'amount' => $amount === null ? null : str_replace(',', '.', str_replace([' ', "\u{00A0}"], '', $amount)),
            'paid_at' => $text($input['paid_at'] ?? null),
            'method' => $text($input['method'] ?? null) ?? PaymentMethod::BankTransfer->value,
            'note' => $text($input['note'] ?? null),
        ];
    }

    private static function money(string $amount): string
    {
        return number_format((float) $amount, 2, ',', "\u{00A0}")."\u{00A0}Kč";
    }
}
