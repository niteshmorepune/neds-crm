<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Http\Requests\PaymentStoreRequest;
use App\Models\Invoice;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::query()
            ->with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
            'filters' => $request->only('status'),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'items', 'payments.recordedBy', 'quotation']);

        return view('invoices.show', [
            'invoice' => $invoice,
            'modes' => PaymentMode::cases(),
        ]);
    }

    public function pdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'items']);

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);

        // Invoice numbers contain "/" which is illegal in a download filename.
        $filename = str_replace('/', '-', $invoice->invoice_number).'.pdf';

        return $pdf->stream($filename);
    }

    public function storePayment(PaymentStoreRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('recordPayment', $invoice);

        $amount = Money::toPaise($request->validated()['amount']);

        if ($amount > $invoice->balance()) {
            return back()->withErrors(['amount' => 'Payment exceeds the outstanding balance of '.Money::format($invoice->balance()).'.']);
        }

        $invoice->payments()->create([
            'paid_on' => $request->validated()['paid_on'],
            'mode' => $request->validated()['mode'],
            'reference' => $request->validated()['reference'] ?? null,
            'amount' => $amount,
            'recorded_by' => $request->user()->id,
        ]);

        $invoice->refreshPaymentStatus();

        return back()->with('status', 'Payment recorded.');
    }

    /**
     * Outstanding receivables grouped by customer (report stub).
     */
    public function receivables(): View
    {
        $this->authorize('viewAny', Invoice::class);

        $rows = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Draft->value, InvoiceStatus::Sent->value,
                InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value,
            ])
            ->with('customer')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($invoices) => [
                'customer' => $invoices->first()->customer,
                'count' => $invoices->count(),
                'outstanding' => $invoices->sum(fn (Invoice $i) => $i->balance()),
            ])
            ->sortByDesc('outstanding')
            ->values();

        return view('reports.receivables', [
            'rows' => $rows,
            'total' => $rows->sum('outstanding'),
        ]);
    }
}
