<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Illuminate\Http\RedirectResponse;
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
