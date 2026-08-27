<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * The public, permanent quotation-view link a Quotation's WhatsApp send
 * (step 5 of the post-payment VA conversion pipeline — see
 * SendQuotationWhatsAppJob) points to — no login required, authorized
 * purely by knowing the unguessable public_token, same pattern as
 * VisibilityAuditReportController. Reuses the exact same PDF generation
 * QuotationController::pdf() uses for the internal staff view.
 */
class QuotationPublicController extends Controller
{
    public function show(string $token): Response
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();
        $quotation->load(['customer', 'items']);

        $pdf = Pdf::loadView('quotations.pdf', ['quotation' => $quotation]);

        $filename = $quotation->number
            ? str_replace('/', '-', $quotation->number).'.pdf'
            : 'quotation-'.$quotation->id.'.pdf';

        return $pdf->stream($filename);
    }
}
