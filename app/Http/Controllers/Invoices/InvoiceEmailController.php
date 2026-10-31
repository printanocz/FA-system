<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Documents\SendInvoiceEmail;
use App\Domain\Documents\DocumentType;
use App\Domain\Documents\Exceptions\DocumentException;
use App\Domain\Mail\EmailDraftBuilder;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Odeslání faktury nebo upomínky e-mailem z Sheetu „Odeslat e-mailem“. */
class InvoiceEmailController extends Controller
{
    public function store(Request $request, Document $invoice, SendInvoiceEmail $send): RedirectResponse
    {
        try {
            $log = $send($invoice, $request->all());
        } catch (DocumentException $e) {
            return back()->with('error', ['message' => $e->getMessage()]);
        }

        return back()->with('status', $log->type === EmailLog::TYPE_REMINDER ? 'Upomínka odeslána' : 'E-mail odeslán');
    }

    /** Předvyplněný e-mail (JSON) pro Sheet „Odeslat e-mailem“ mimo detail faktury, např. z Přehledu. */
    public function draft(Document $invoice, EmailDraftBuilder $drafts): JsonResponse
    {
        abort_unless($invoice->type === DocumentType::Invoice, 404);

        $invoice->loadMissing('contact');

        return response()->json($drafts->build(
            $invoice,
            $invoice->contact,
            $invoice->computedStatus(Carbon::today())?->value === 'overdue',
        ));
    }
}
