<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Http\Requests\InvoiceLogImportRequest;
use App\Http\Requests\InvoiceLogStoreRequest;
use App\Http\Requests\InvoiceLogUpdateRequest;
use App\Http\Requests\InvoicePaymentPromiseUpdateRequest;
use App\Http\Requests\PaymentStoreRequest;
use App\Mail\InvoiceIssued;
use App\Mail\PaymentReceived;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Notifications\PaymentRecordedNotification;
use App\Services\CollectionsMetrics;
use App\Services\InvoiceNumberGenerator;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $user = $request->user();

        $invoices = Invoice::query()
            ->with('customer')
            ->when($user->hasRole(UserRole::Sales)
                && ! $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts),
                fn ($q) => $q->whereHas('customer', fn ($c) => $c->visibleTo($user)))
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

    public function create(): View
    {
        $this->authorize('create', Invoice::class);

        return view('invoices.create', [
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'deals' => Deal::whereNotIn('stage', ['lost'])->orderBy('title')->get(['id', 'title', 'customer_id']),
            'projects' => Project::whereNotIn('status', ['completed'])->orderBy('name')->get(['id', 'name', 'customer_id']),
        ]);
    }

    public function store(InvoiceLogStoreRequest $request, InvoiceNumberGenerator $numbers): RedirectResponse
    {
        $issueDate = Carbon::parse($request->validated()['issue_date']);
        $total = Money::toPaise($request->validated()['amount']);

        $data = $request->validated();

        $invoice = Invoice::create([
            'invoice_number' => $data['invoice_number'],
            'financial_year' => $numbers->financialYear($issueDate),
            'customer_id' => $data['customer_id'],
            'deal_id' => $data['deal_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'status' => InvoiceStatus::Sent,
            'issue_date' => $issueDate,
            'due_date' => $data['due_date'] ?? null,
            'subtotal' => $total,
            'taxable_total' => $total,
            'total' => $total,
            'amount_paid' => 0,
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice logged.');
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        return view('invoices.edit', [
            'invoice' => $invoice,
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'deals' => Deal::whereNotIn('stage', ['lost'])->orderBy('title')->get(['id', 'title', 'customer_id']),
            'projects' => Project::whereNotIn('status', ['completed'])->orderBy('name')->get(['id', 'name', 'customer_id']),
        ]);
    }

    public function update(InvoiceLogUpdateRequest $request, Invoice $invoice, InvoiceNumberGenerator $numbers): RedirectResponse
    {
        abort_unless($invoice->isEditable(), 403);

        $issueDate = Carbon::parse($request->validated()['issue_date']);
        $total = Money::toPaise($request->validated()['amount']);

        $data = $request->validated();

        $invoice->update([
            'invoice_number' => $data['invoice_number'],
            'financial_year' => $numbers->financialYear($issueDate),
            'customer_id' => $data['customer_id'],
            'deal_id' => $data['deal_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'issue_date' => $issueDate,
            'due_date' => $data['due_date'] ?? null,
            'subtotal' => $total,
            'taxable_total' => $total,
            'total' => $total,
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice updated.');
    }

    public function import(): View
    {
        $this->authorize('create', Invoice::class);

        return view('invoices.import');
    }

    public function importStore(InvoiceLogImportRequest $request, InvoiceNumberGenerator $numbers): RedirectResponse
    {
        $path = $request->file('csv')->getRealPath();
        $handle = fopen($path, 'r');

        $headers = array_map('trim', fgetcsv($handle) ?: []);
        $headerMap = array_flip(array_map('strtolower', $headers));

        $col = fn (string ...$names): ?int => collect($names)
            ->map(fn ($n) => $headerMap[strtolower($n)] ?? null)
            ->first(fn ($v) => $v !== null);

        $colInvoice = $col('Invoice No', 'invoice_number', 'Voucher No', 'Invoice Number');
        $colDate = $col('Date', 'Invoice Date', 'issue_date');
        $colClient = $col('Client Name', 'Party Name', 'Customer', 'customer_name');
        $colAmount = $col('Amount', 'Total', 'Net Amount', 'Total Amount');
        $colDue = $col('Due Date', 'due_date');

        if ($colInvoice === null || $colDate === null || $colClient === null || $colAmount === null) {
            fclose($handle);

            return back()->withErrors(['csv' => 'CSV must contain columns: Invoice No, Date, Client Name, Amount (and optionally Due Date).']);
        }

        $imported = 0;
        $skipped = [];

        while (($row = fgetcsv($handle)) !== false) {
            $row = array_map('trim', $row);

            $invoiceNo = $row[$colInvoice] ?? null;
            $dateRaw = $row[$colDate] ?? null;
            $clientRaw = $row[$colClient] ?? null;
            $amountRaw = $row[$colAmount] ?? null;
            $dueRaw = $colDue !== null ? ($row[$colDue] ?? null) : null;

            if (! $invoiceNo || ! $dateRaw || ! $clientRaw || ! $amountRaw) {
                $skipped[] = "Row skipped — missing required value (invoice: {$invoiceNo})";

                continue;
            }

            // Skip duplicates silently.
            if (Invoice::where('invoice_number', $invoiceNo)->exists()) {
                $skipped[] = "{$invoiceNo} — already exists, skipped";

                continue;
            }

            $customer = Customer::whereRaw('LOWER(TRIM(company_name)) = ?', [strtolower(trim($clientRaw))])->first();
            if (! $customer) {
                $skipped[] = "{$invoiceNo} — client \"{$clientRaw}\" not found";

                continue;
            }

            try {
                $issueDate = Carbon::createFromFormat('d/m/Y', $dateRaw)
                    ?? Carbon::createFromFormat('Y-m-d', $dateRaw);
            } catch (\Throwable) {
                $skipped[] = "{$invoiceNo} — invalid date \"{$dateRaw}\"";

                continue;
            }

            $dueDate = null;
            if ($dueRaw) {
                try {
                    $dueDate = Carbon::createFromFormat('d/m/Y', $dueRaw)
                        ?? Carbon::createFromFormat('Y-m-d', $dueRaw);
                } catch (\Throwable) {
                    // non-fatal: just skip due date
                }
            }

            $amount = (float) str_replace([',', '₹', ' '], '', $amountRaw);
            if ($amount <= 0) {
                $skipped[] = "{$invoiceNo} — invalid amount \"{$amountRaw}\"";

                continue;
            }

            $total = Money::toPaise($amount);

            Invoice::create([
                'invoice_number' => $invoiceNo,
                'financial_year' => $numbers->financialYear($issueDate),
                'customer_id' => $customer->id,
                'status' => InvoiceStatus::Sent,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => $total,
                'taxable_total' => $total,
                'total' => $total,
                'amount_paid' => 0,
            ]);

            $imported++;
        }

        fclose($handle);

        $message = "{$imported} invoice(s) imported.";
        if ($skipped) {
            $message .= ' '.count($skipped).' row(s) skipped: '.implode('; ', array_slice($skipped, 0, 5));
            if (count($skipped) > 5) {
                $message .= ' … and '.(count($skipped) - 5).' more.';
            }
        }

        return redirect()->route('invoices.index')->with('status', $message);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer.contacts', 'items', 'payments.recordedBy', 'quotation']);

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

        $filename = $invoice->invoice_number
            ? str_replace('/', '-', $invoice->invoice_number).'.pdf'
            : 'invoice-'.$invoice->id.'.pdf';

        return $pdf->stream($filename);
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $invoice->invoice_number) {
            return back()->withErrors(['send' => 'Assign an invoice number before sending.']);
        }

        $email = $invoice->customer->load('contacts')->billingEmail();

        if (! $email) {
            return back()->withErrors(['send' => 'No billing email found for this client.']);
        }

        Mail::to($email)->send(new InvoiceIssued($invoice->load(['customer', 'items'])));

        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->update(['status' => InvoiceStatus::Sent]);
        }

        return back()->with('status', "Invoice sent to {$email}.");
    }

    public function storePayment(PaymentStoreRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('recordPayment', $invoice);

        $amount = Money::toPaise($request->validated()['amount']);
        $tds = Money::toPaise($request->validated()['tds_amount'] ?? 0);

        if ($amount + $tds > $invoice->balance()) {
            return back()->withErrors(['amount' => 'Payment exceeds the outstanding balance of '.Money::format($invoice->balance()).'.']);
        }

        $payment = $invoice->payments()->create([
            'paid_on' => $request->validated()['paid_on'],
            'mode' => $request->validated()['mode'],
            'reference' => $request->validated()['reference'] ?? null,
            'amount' => $amount,
            'tds_amount' => $tds,
            'recorded_by' => $request->user()->id,
        ]);

        $invoice->refreshPaymentStatus();

        // Notify accounts staff + client's sales rep (skip the person who recorded it).
        $recorder = $request->user();
        $notification = new PaymentRecordedNotification($invoice, $payment);
        $recipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Accounts)
            ->where('id', '!=', $recorder->id)
            ->get();
        $ownerId = Customer::where('id', $invoice->customer_id)->value('owner_id');
        if ($ownerId && $ownerId !== $recorder->id) {
            $owner = User::find($ownerId);
            if ($owner && ! $recipients->contains('id', $owner->id)) {
                $recipients = $recipients->push($owner);
            }
        }
        $recipients->each(fn (User $u) => $u->notify($notification));

        $status = 'Payment recorded.';

        if ($request->boolean('send_receipt')) {
            $invoice->loadMissing(['customer.contacts']);
            $email = $invoice->customer?->contacts->where('is_primary', true)->first()?->email
                ?? $invoice->customer?->contacts->first()?->email;

            if ($email) {
                Mail::to($email)->send(new PaymentReceived($invoice, $payment));
                $status = 'Payment recorded and receipt sent to client.';
            }
        }

        return back()->with('status', $status);
    }

    /**
     * Set or clear the date a client promised to pay by — logged manually by
     * whoever takes the call, so a "I'll pay in a day or two" doesn't get lost.
     * Same accounts-team gate as recording a payment.
     */
    public function updatePaymentPromise(InvoicePaymentPromiseUpdateRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('recordPayment', $invoice);

        $promisedDate = $request->validated()['payment_promised_date'] ?? null;
        $invoice->update(['payment_promised_date' => $promisedDate]);

        return back()->with('status', $promisedDate ? 'Payment promise date saved.' : 'Payment promise date cleared.');
    }

    /**
     * Assign the next GST invoice number. Only Accounts / Admin can do this.
     * Invoices created from quotations start with no number; this finalises them.
     */
    public function assignNumber(Invoice $invoice, InvoiceNumberGenerator $numbers): RedirectResponse
    {
        $this->authorize('recordPayment', $invoice); // accounts-only gate (reuses same policy check)

        if ($invoice->invoice_number !== null) {
            return back()->with('error', 'This invoice already has a number assigned.');
        }

        $invoice->update([
            'invoice_number' => $numbers->generate($invoice->issue_date),
            'financial_year' => $numbers->financialYear($invoice->issue_date),
        ]);

        return back()->with('status', "Invoice number {$invoice->invoice_number} assigned.");
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->items()->delete();
        $invoice->payments()->delete(); // soft-deleted, recoverable alongside the invoice
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Invoice deleted.');
    }

    /**
     * Outstanding receivables grouped by customer (report stub).
     */
    public function receivables(CollectionsMetrics $collectionsMetrics): View
    {
        $this->authorize('viewAny', Invoice::class);

        // Deliberately does NOT exclude invoices whose customer has been
        // soft-deleted — the row still shows, labelled "Client removed" in
        // the view (same fallback invoices/index.blade.php already uses),
        // rather than silently vanishing from the total (see CLAUDE.md's
        // decision log for the 2026-07-24 incident this fixed).
        $rows = $collectionsMetrics->outstandingInvoicesQuery()
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
