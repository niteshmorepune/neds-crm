<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationMilestone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-client collections + delivery snapshot, scoped to a referring partner,
 * to direct (unassigned) clients, or company-wide. Answers the operational
 * question BusinessOverviewMetrics::partnerPerformance() doesn't: which
 * clients haven't paid, how much is only partially paid, and — for
 * milestone-billed projects — whether the team has actually finished the
 * current phase of work and it's time to raise the next invoice.
 */
class CollectionsMetrics
{
    /**
     * @param  int|null  $partnerId  Scope to one referring partner. Takes priority over $directOnly.
     * @param  bool  $directOnly  Scope to clients with no referring partner.
     * @return Collection<int, array<string, mixed>>
     */
    public function clientHealth(?int $partnerId = null, bool $directOnly = false): Collection
    {
        $today = Carbon::today();

        $query = Customer::query()->with('referringPartner');
        if ($partnerId !== null) {
            $query->where('referring_partner_id', $partnerId);
        } elseif ($directOnly) {
            $query->whereNull('referring_partner_id');
        }

        return $query->get()->map(function (Customer $customer) use ($today) {
            $invoices = $customer->invoices()
                ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value, InvoiceStatus::Draft->value])
                ->get()
                ->filter(fn (Invoice $invoice) => $invoice->balance() > 0);

            // MarkOverdueInvoices promotes BOTH never-paid and partially-paid
            // invoices to Overdue once past due date, so status alone can't
            // distinguish "hasn't paid a rupee" from "paid some, still owes
            // some" — split on amount_paid instead, which is what the team
            // actually means by these two separate questions. A fully-unpaid
            // non-recurring invoice (typically a milestone bill) gets its own
            // bucket too, rather than being silently dropped — it's still
            // collections-relevant even though it isn't what the team means
            // by "recurring".
            $unpaid = $invoices->filter(fn (Invoice $invoice) => (int) $invoice->amount_paid === 0 && $invoice->status === InvoiceStatus::Overdue);
            $recurringOverdue = $unpaid->filter(fn (Invoice $invoice) => $invoice->isRecurring());
            $otherUnpaid = $unpaid->reject(fn (Invoice $invoice) => $invoice->isRecurring());
            $partial = $invoices->filter(fn (Invoice $invoice) => (int) $invoice->amount_paid > 0);

            $oldestOverdueDays = $invoices
                ->filter(fn (Invoice $invoice) => $invoice->due_date !== null)
                ->map(fn (Invoice $invoice) => (int) $invoice->due_date->copy()->startOfDay()->diffInDays($today, false))
                ->filter(fn (int $days) => $days > 0)
                ->max();

            $nextPromise = $invoices
                ->filter(fn (Invoice $invoice) => $invoice->payment_promised_date !== null)
                ->sortBy('payment_promised_date')
                ->first();

            $projects = $customer->projects()
                ->where('status', ProjectStatus::Active->value)
                ->get()
                ->map(function (Project $project) {
                    $milestone = $this->nextUnbilledMilestone($project);

                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'service' => $project->service?->name,
                        'completion_percentage' => $project->completionPercentage(),
                        'milestone' => $milestone ? [
                            'title' => $milestone->title,
                            'percentage' => (float) $milestone->percentage,
                            'status' => $milestone->status,
                            'ready_to_invoice' => $milestone->readyToInvoice(),
                        ] : null,
                    ];
                })->values();

            return [
                'customer' => $customer,
                'partner' => $customer->referringPartner?->name,
                'recurring_overdue_count' => $recurringOverdue->count(),
                'recurring_overdue_amount' => (int) $recurringOverdue->sum(fn (Invoice $invoice) => $invoice->balance()),
                'other_unpaid_count' => $otherUnpaid->count(),
                'other_unpaid_amount' => (int) $otherUnpaid->sum(fn (Invoice $invoice) => $invoice->balance()),
                'partial_count' => $partial->count(),
                'partial_amount' => (int) $partial->sum(fn (Invoice $invoice) => $invoice->balance()),
                'oldest_overdue_days' => $oldestOverdueDays,
                'oldest_overdue_months' => $oldestOverdueDays !== null ? round($oldestOverdueDays / 30, 1) : null,
                'payment_promised_date' => $nextPromise?->payment_promised_date,
                'promise_broken' => $invoices->contains(fn (Invoice $invoice) => $invoice->promiseBroken()),
                'projects' => $projects,
            ];
        })->reject(fn (array $row) => $row['recurring_overdue_count'] === 0
            && $row['other_unpaid_count'] === 0
            && $row['partial_count'] === 0
            && $row['projects']->isEmpty()
        )->sortByDesc(fn (array $row) => $row['recurring_overdue_amount'] + $row['other_unpaid_amount'] + $row['partial_amount'])
            ->values();
    }

    /**
     * Start of the trailing-6-calendar-month window (current month + 5 prior,
     * oldest first) used by both billedByClient() and billedByMonth(), so the
     * two totals always agree. Matches the alignment SalesPipelineMetrics::
     * wonValueTrend() uses for its own trailing-month windows.
     */
    private function sixMonthWindowStart(): Carbon
    {
        return Carbon::now()->startOfMonth()->subMonths(5);
    }

    /**
     * Invoices issued to one partner's referred clients within the trailing
     * 6-month window — "billed" means issued, so it includes paid/overdue/
     * partial invoices and excludes Draft and Cancelled, matching
     * ReportMetrics::revenue()'s definition of billed/invoiced revenue.
     */
    private function billedInvoicesForPartner(int $partnerId): Collection
    {
        return Invoice::query()
            ->whereHas('customer', fn ($q) => $q->where('referring_partner_id', $partnerId))
            ->where('issue_date', '>=', $this->sixMonthWindowStart())
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->with('customer')
            ->get();
    }

    /**
     * Per-client invoiced totals for one partner's referred clients over the
     * trailing 6 months. Every referred client is included, even ones billed
     * nothing in the window, so this can't be mistaken for the (deliberately
     * noisier) clientHealth() list, which drops clients with nothing
     * outstanding.
     *
     * @return Collection<int, array{customer: Customer, invoice_count: int, amount: int}>
     */
    public function billedByClient(int $partnerId): Collection
    {
        $invoices = $this->billedInvoicesForPartner($partnerId)->groupBy('customer_id');

        return Customer::query()
            ->where('referring_partner_id', $partnerId)
            ->get()
            ->map(fn (Customer $customer) => [
                'customer' => $customer,
                'invoice_count' => $invoices->get($customer->id, collect())->count(),
                'amount' => (int) $invoices->get($customer->id, collect())->sum('total'),
            ])
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * Total billed per calendar month for one partner's referred clients,
     * trailing 6 months oldest-first, backfilling any month with zero
     * invoices rather than omitting it (so the run of 6 bars/rows never
     * shifts on the page).
     *
     * @return list<array{month: string, label: string, invoice_count: int, amount: int}>
     */
    public function billedByMonth(int $partnerId): array
    {
        $start = $this->sixMonthWindowStart();
        $byMonth = $this->billedInvoicesForPartner($partnerId)->groupBy(fn (Invoice $i) => $i->issue_date->format('Y-m'));

        return collect(range(0, 5))->map(function (int $i) use ($start, $byMonth) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $group = $byMonth->get($key, collect());

            return [
                'month' => $key,
                'label' => $month->format('M Y'),
                'invoice_count' => $group->count(),
                'amount' => (int) $group->sum('total'),
            ];
        })->all();
    }

    /**
     * The project's active project can only be tied back to its billing
     * milestones via the shared Deal — there's no direct Project→Quotation
     * link today. Takes the latest Accepted quotation on that deal that
     * actually has milestones (a deal could in principle have more than
     * one quotation; this is the best available signal, not a guarantee).
     */
    private function nextUnbilledMilestone(Project $project): ?QuotationMilestone
    {
        if ($project->deal_id === null) {
            return null;
        }

        $quotation = Quotation::query()
            ->where('deal_id', $project->deal_id)
            ->where('status', QuotationStatus::Accepted->value)
            ->whereHas('milestones')
            ->latest()
            ->first();

        return $quotation?->milestones()
            ->whereNull('invoice_id')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * All invoices considered "outstanding" — Draft/Sent/PartiallyPaid/
     * Overdue. Deliberately does NOT exclude invoices whose customer has
     * been soft-deleted (an earlier version of the Receivables Report did,
     * to avoid a null-pointer crash — but that also silently hid real
     * unpaid money from both the report and the Accounts dashboard tile,
     * and let the two drift out of sync with each other). Callers should
     * render a soft-deleted customer the same "Client removed" way
     * invoices/index.blade.php already does, not exclude the row.
     *
     * Single source of truth for "how much are we owed" — both
     * InvoiceController::receivables() and DashboardMetrics::accountsStats()
     * call this, so they can never disagree again.
     */
    public function outstandingInvoicesQuery(): Builder
    {
        return Invoice::query()->whereIn('status', [
            InvoiceStatus::Draft->value, InvoiceStatus::Sent->value,
            InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value,
        ]);
    }
}
