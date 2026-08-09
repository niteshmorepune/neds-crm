<?php

namespace App\Livewire;

use App\Enums\ContractRenewalStatus;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Full-page dashboard (routes/web.php: contract-renewals.index) surfacing
 * active recurring templates whose end_date falls within a 30/60/90-day
 * window, with an inline, manually-driven renewal-conversation status per
 * template (App\Enums\ContractRenewalStatus) — separate from
 * RecurringInvoice::dashboardStatus(), which is billing/payment state, not
 * "have we talked to the client about renewing yet." Gated the same way as
 * the rest of the recurring-invoice screens: viewing follows
 * InvoicePolicy::viewAny (accounts team, or Sales/others granted the
 * "invoices" menu item), moving the status follows InvoicePolicy::create
 * (accounts team or Sales) — the same pair RecurringInvoiceController's own
 * mutating actions already use.
 */
#[Layout('layouts.app')]
class ContractRenewalDashboard extends Component
{
    public int $window = 30;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
    }

    public function setWindow(int $days): void
    {
        abort_unless(in_array($days, [30, 60, 90], true), 422);

        $this->window = $days;
    }

    public function setStatusFilter(string $status): void
    {
        abort_unless($status === '' || in_array($status, ContractRenewalStatus::values(), true), 422);

        $this->statusFilter = $status;
    }

    public function updateStatus(int $recurringInvoiceId, string $status): void
    {
        $this->authorize('create', Invoice::class);
        abort_unless(in_array($status, ContractRenewalStatus::values(), true), 422);

        $recurring = RecurringInvoice::renewingWithin($this->window)->findOrFail($recurringInvoiceId);
        $recurring->update(['renewal_status' => $status]);
    }

    public function render()
    {
        $inWindow = RecurringInvoice::with(['customer', 'service', 'items'])
            ->renewingWithin($this->window)
            ->orderBy('end_date')
            ->get();

        $counts = collect(ContractRenewalStatus::cases())
            ->mapWithKeys(fn (ContractRenewalStatus $status) => [
                $status->value => $inWindow->where('renewal_status', $status)->count(),
            ]);

        $templates = $this->statusFilter === ''
            ? $inWindow
            : $inWindow->where('renewal_status', ContractRenewalStatus::from($this->statusFilter));

        return view('livewire.contract-renewal-dashboard', [
            'templates' => $templates,
            'counts' => $counts,
            'atRiskMrr' => (int) $inWindow->sum(fn (RecurringInvoice $t) => $t->monthlyEquivalentValue()),
            'statuses' => ContractRenewalStatus::cases(),
        ]);
    }
}
