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
}
