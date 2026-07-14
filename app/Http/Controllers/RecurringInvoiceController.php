<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RecurringInvoiceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Invoice::class);

        $recurring = RecurringInvoice::with(['customer', 'service'])
            ->orderByDesc('is_active')
            ->latest()
            ->paginate(15);

        return view('recurring-invoices.index', ['recurring' => $recurring]);
    }

    public function show(RecurringInvoice $recurring): View
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $recurring->invoices()->with('payments')->latest('issue_date')->paginate(10);

        return view('recurring-invoices.show', compact('recurring', 'invoices'));
    }

    public function generateNow(RecurringInvoice $recurring, InvoiceNumberGenerator $numbers): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        if (! $recurring->is_active) {
            return back()->with('error', 'Cannot generate: this recurring invoice is paused.');
        }

        // Prevent a duplicate if an invoice was already generated today.
        if ($recurring->invoices()->whereDate('issue_date', today())->exists()) {
            return back()->with('error', 'An invoice was already generated for this cycle today.');
        }

        $issueDate = now()->startOfDay();
        $recurring->load(['items', 'customer']);

        $invoice = DB::transaction(function () use ($recurring, $numbers, $issueDate) {
            $invoice = Invoice::create([
                'invoice_number' => $numbers->generate($issueDate),
                'financial_year' => $numbers->financialYear($issueDate),
                'customer_id' => $recurring->customer_id,
                'recurring_invoice_id' => $recurring->id,
                'status' => InvoiceStatus::Sent->value,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays(15)->toDateString(),
                'place_of_supply_state_code' => $recurring->customer->state_code,
                'discount' => $recurring->discount,
                'is_gst_exempt' => $recurring->customer->gst_exempt,
            ]);

            foreach ($recurring->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'sac_code' => $item->sac_code,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'gst_rate' => $item->gst_rate,
                    'amount' => (int) round(((float) $item->quantity) * (int) $item->rate),
                    'sort_order' => $item->sort_order,
                ]);
            }

            $invoice->refresh()->recalculateTotals();

            return $invoice;
        });

        // Advance the schedule.
        $next = $recurring->frequency->advance($recurring->next_run_on);
        $recurring->next_run_on = $next;
        if ($recurring->end_date && $next->gt($recurring->end_date)) {
            $recurring->is_active = false;
        }
        $recurring->save();

        if ($email = $recurring->customer->billingEmail()) {
            Mail::to($email)->send(new InvoiceIssued($invoice));
        }

        return redirect()
            ->route('recurring-invoices.show', $recurring)
            ->with('status', "Invoice {$invoice->invoice_number} generated and emailed to the client.");
    }

    public function toggle(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $recurring->update(['is_active' => ! $recurring->is_active]);

        return back()->with('status', $recurring->is_active ? 'Activated.' : 'Paused.');
    }

    public function destroy(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $recurring->items()->delete();
        $recurring->delete();

        return redirect()->route('recurring-invoices.index')->with('status', 'Recurring invoice removed.');
    }
}
