<?php

namespace App\Http\Controllers\Portal;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends PortalController
{
    public function index(): View
    {
        return view('portal.invoices.index', [
            'invoices' => $this->customer()->invoices()->latest()->paginate(15),
        ]);
    }

    public function show(int $invoice): View
    {
        // Scoped to the contact's customer — findOrFail 404s on another's invoice.
        $invoice = $this->customer()->invoices()->with('items', 'payments')->findOrFail($invoice);

        return view('portal.invoices.show', ['invoice' => $invoice]);
    }

    public function pdf(int $invoice): Response
    {
        $invoice = $this->customer()->invoices()->with('items', 'customer')->findOrFail($invoice);

        $filename = str_replace('/', '-', $invoice->invoice_number).'.pdf';

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice])->stream($filename);
    }
}
