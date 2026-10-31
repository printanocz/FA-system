<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Documents\SendInvoiceEmail;
use App\Domain\Documents\Exceptions\DocumentException;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EmailLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
}
