<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QuotationController extends PartnerPortalController
{
    /**
     * Download a quotation PDF — scoped to the logged-in partner's own
     * referred clients only, never another partner's.
     */
    public function pdf(Quotation $quotation): Response
    {
        abort_unless($this->partner()->ownsQuotation($quotation), 403);

        $quotation->load(['customer', 'items']);

        $pdf = Pdf::loadView('quotations.pdf', ['quotation' => $quotation]);

        $filename = $quotation->number
            ? str_replace('/', '-', $quotation->number).'.pdf'
            : 'quotation-'.$quotation->id.'.pdf';

        return $pdf->stream($filename);
    }
}
