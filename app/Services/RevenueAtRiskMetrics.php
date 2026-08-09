<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\RecurringInvoice;
use Illuminate\Support\Collection;

/**
 * Manager panel doc's "Revenue at Risk" (Tier 2 #10): "AR aging, MRR
 * snapshot and Client Radar each hold a piece of this. No single '₹ at
 * risk, click to see why' figure exists yet." Pure aggregation — no new
 * formula decision needed (unlike the three items before this one), so no
 * AskUserQuestion for this one. Every figure below is computed by an
 * existing method; this class only adds the labels/routes and sums them.
 *
 * Deliberately kept as three separate buckets rather than one blended
 * total shown alone — a client can be both "has an overdue invoice" (AR
 * aging bucket) and "Client Radar flagged" (at-risk-client bucket) at the
 * same time, and collapsing those into a single deduplicated number would
 * need new logic this class isn't meant to add. The headline total is a
 * simple sum of the three, which can double-count that overlap — shown
 * that way deliberately, with each bucket's own drill-down link so a
 * manager can always see exactly which invoices/contracts/clients make up
 * the number in front of them.
 */
class RevenueAtRiskMetrics
{
    public function __construct(
        private readonly BusinessOverviewMetrics $overview,
        private readonly ClientRadarService $radar,
    ) {}

    /**
     * @return Collection<int, array{key: string, label: string, detail: string, amount: int, route: string}>
     */
    public function signals(): Collection
    {
        return collect([
            [
                'key' => 'overdue_receivables',
                'label' => 'Overdue receivables',
                'detail' => 'Billed invoices past their due date, not yet paid',
                'amount' => $this->overdueReceivables(),
                'route' => route('reports.receivables'),
            ],
            [
                'key' => 'renewals_due_mrr',
                'label' => 'MRR renewing in 30 days',
                'detail' => 'Monthly-equivalent value of active contracts ending within 30 days',
                'amount' => $this->renewalsDueMrr(),
                'route' => route('contract-renewals.index'),
            ],
            [
                'key' => 'at_risk_client_mrr',
                'label' => 'MRR tied to at-risk clients',
                'detail' => 'Recurring revenue from clients Client Radar has flagged',
                'amount' => $this->atRiskClientMrr(),
                'route' => route('client-radar.index'),
            ],
        ]);
    }

    public function totalAtRisk(): int
    {
        return (int) $this->signals()->sum('amount');
    }

    /**
     * Every AR aging bucket except 'current' (not yet due) — the same
     * bucket definitions BusinessOverviewMetrics::arAging() already powers
     * on the Business Overview report.
     */
    private function overdueReceivables(): int
    {
        return (int) collect($this->overview->arAging()['buckets'])
            ->reject(fn (array $bucket) => $bucket['key'] === 'current')
            ->sum('total');
    }

    /**
     * Same 30-day window and per-template formula as the Contract & Renewal
     * Dashboard's own "MRR at stake" tile.
     */
    private function renewalsDueMrr(): int
    {
        return (int) RecurringInvoice::with('items')
            ->renewingWithin(30)
            ->get()
            ->sum(fn (RecurringInvoice $template) => $template->monthlyEquivalentValue());
    }

    /**
     * Total MRR across every Client-Radar-flagged customer, via
     * Customer::monthlyRecurringValue() (the same single source of truth
     * the Client 360 summary strip uses) — not ClientRadarService's own
     * eager-loaded customers, which don't carry recurringInvoices.items.
     */
    private function atRiskClientMrr(): int
    {
        $flaggedIds = $this->radar->flaggedClients()->pluck('customer.id');

        return (int) Customer::whereIn('id', $flaggedIds)
            ->with('recurringInvoices.items')
            ->get()
            ->sum(fn (Customer $customer) => $customer->monthlyRecurringValue());
    }
}
