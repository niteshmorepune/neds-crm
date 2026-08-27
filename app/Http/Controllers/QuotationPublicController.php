<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The public, permanent quotation-view page a Quotation's WhatsApp/email
 * send points to — no login required, authorized purely by knowing the
 * unguessable public_token, same pattern as VisibilityAuditReportController.
 * show() renders a summary page (line items, milestones, an online
 * "Pay Advance" button when eligible — step 6 of the post-payment VA
 * conversion pipeline, though this applies to every milestone-billed
 * quotation, not just VA-originated ones); download() streams the same PDF
 * QuotationController::pdf() generates for the internal staff view.
 */
class QuotationPublicController extends Controller
{
    public function show(string $token): View
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();
        $quotation->load(['customer', 'items', 'milestones.invoice']);

        return view('quotations.public-view', [
            'quotation' => $quotation,
            'milestone' => $quotation->nextPayableMilestone(),
            'razorpayConfigured' => (bool) config('services.razorpay.key_id'),
        ]);
    }

    public function download(string $token): Response
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
