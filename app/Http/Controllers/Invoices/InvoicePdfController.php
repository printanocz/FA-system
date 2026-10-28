<?php

namespace App\Http\Controllers\Invoices;

use App\Domain\Pdf\InvoicePdf;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * PDF faktury. Vystavený doklad vrací archivní soubor, koncept se generuje za běhu
 * s vodoznakem a neukládá. Route binding `invoice` pouští jen faktury (jinak 404).
 */
class InvoicePdfController extends Controller
{
    public function show(Request $request, Document $invoice, InvoicePdf $pdf): Response
    {
        $disposition = $request->boolean('stahnout') ? 'attachment' : 'inline';

        return response($pdf->contents($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $pdf->filename($invoice), 'Faktura.pdf'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
