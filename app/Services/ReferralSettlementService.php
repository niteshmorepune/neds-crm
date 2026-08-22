<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PartnerCollectionMode;
use App\Enums\ReferralShareType;
use App\Enums\SettlementAmountSource;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Models\ReferralSettlement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The referral settlement grid + ledger — how much of each recurring
 * service's monthly billing is NEDS's/Partner's share, and whether that
 * share has actually changed hands. Deliberately separate from whether the
 * END CLIENT has paid NEDS: for a NedsCollects client, that's already fully
 * tracked by the real Invoice/Payment rows this service reads (never
 * duplicated into ReferralSettlement); for a PartnerCollects client there is
 * no NEDS invoice at all, so the manually-entered billed_amount here IS the
 * only record of what was collected.
 */
class ReferralSettlementService
{
    /** Sum of real Invoice totals for this template, issued within the given month. */
    public function billedAmountFromInvoices(RecurringInvoice $template, Carbon $monthStart): int
    {
        return (int) $template->invoices()
            ->whereBetween('issue_date', [$monthStart->copy()->startOfMonth(), $monthStart->copy()->endOfMonth()])
            ->sum('total');
    }

    /**
     * Per-service x per-month grid for one client: trailing $months
     * (oldest-first) through the current month, PLUS one genuinely future
     * "upcoming" month — mirrors the owner's manual Excel sheet exactly
     * (client -> service -> month -> paid/pending/upcoming). $months only
     * controls how far back the grid goes; the upcoming month is always
     * exactly one beyond the current month, not part of that count.
     *
     * Requires $customer's recurringInvoices.invoices and
     * referralSettlements to already be eager-loaded (same "already loaded"
     * requirement as nonOrphanedRecurringInvoices() itself) — avoids an N+1
     * across every template x month cell.
     *
     * @return list<array{recurring_invoice: RecurringInvoice, service_name: string, rows: list<array{period: string, label: string, billing_status: string, amount: ?int, settlement: ?ReferralSettlement}>}>
     */
    public function gridForClient(Customer $customer, int $months = 6): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subMonths($months - 1)->startOfMonth();
        $isPartnerCollected = $customer->isPartnerCollected();

        // A reseller-billed client's own templates carry the billing
        // customer's id, not this client's (Customer::billingTarget()) —
        // nonOrphanedRecurringInvoices() alone would show this client's row
        // as empty even with real active billing. Found instead via
        // project_id on this client's own Projects, same bridge used
        // everywhere else this gap was fixed (see the 2026-08-22
        // reseller-billing entries in CLAUDE.md's decisions log).
        $customer->loadMissing('projects');
        $projectIds = $customer->projects->pluck('id');
        $reselleredTemplates = $projectIds->isNotEmpty()
            ? RecurringInvoice::whereIn('project_id', $projectIds)
                ->where('customer_id', '!=', $customer->id)
                ->with(['service', 'invoices'])
                ->get()
                ->reject(fn (RecurringInvoice $r) => $r->isOrphaned())
            : collect();

        $templates = $customer->nonOrphanedRecurringInvoices()->concat($reselleredTemplates);
        $settlementsByKey = $customer->referralSettlements
            ->groupBy(fn (ReferralSettlement $s) => $s->recurring_invoice_id.'|'.$s->period_start->format('Y-m'));

        return $templates->map(function (RecurringInvoice $template) use ($start, $today, $months, $settlementsByKey, $isPartnerCollected) {
            // $months trailing slots (oldest..current) + 1 always-future "upcoming" slot.
            $rows = collect(range(0, $months))->map(function (int $i) use ($start, $today, $template, $settlementsByKey, $isPartnerCollected) {
                $monthStart = $start->copy()->addMonths($i);
                $key = $monthStart->format('Y-m');
                $settlement = $settlementsByKey->get($template->id.'|'.$key)?->first();

                if ($monthStart->gt($today->copy()->startOfMonth())) {
                    return [
                        'period' => $key, 'label' => $monthStart->format('M Y'),
                        'billing_status' => 'upcoming', 'amount' => null, 'settlement' => $settlement,
                    ];
                }

                if ($isPartnerCollected) {
                    return [
                        'period' => $key, 'label' => $monthStart->format('M Y'),
                        'billing_status' => $settlement ? 'collected' : 'none',
                        'amount' => $settlement?->billed_amount, 'settlement' => $settlement,
                    ];
                }

                $invoice = $template->invoices->first(fn ($inv) => $inv->issue_date->format('Y-m') === $key);

                return [
                    'period' => $key, 'label' => $monthStart->format('M Y'),
                    'billing_status' => $invoice === null ? 'none' : ($invoice->status === InvoiceStatus::Paid ? 'paid' : 'pending'),
                    'amount' => $invoice?->total, 'settlement' => $settlement,
                ];
            })->all();

            return [
                'recurring_invoice' => $template,
                'service_name' => $template->service?->name ?? '—',
                'rows' => $rows,
            ];
        })->values()->all();
    }

    /**
     * Staff-entered monthly collection for a PartnerCollects client — this
     * figure IS the source of truth, there's no invoice to reconcile
     * against. Create-or-update, not a draft/finalize workflow: saving
     * immediately finalizes (nothing to revise from beforehand).
     */
    public function recordManualBilling(RecurringInvoice $template, Carbon $monthStart, int $amountPaise, User $enteredBy): ReferralSettlement
    {
        $customer = $template->customer;
        $monthStart = $monthStart->copy()->startOfMonth();

        $values = [
            'customer_id' => $template->customer_id,
            'partner_id' => $customer->referring_partner_id,
            'flow_mode' => PartnerCollectionMode::PartnerCollects,
            'billed_amount' => $amountPaise,
            // A FixedAmount client has no meaningful percentage — 0 here,
            // never used for display (views branch on the customer's own
            // referral_share_type instead of inferring it from this column).
            'share_rate' => $customer->referral_share_type === ReferralShareType::FixedAmount
                ? 0 : (float) ($customer->referral_share_rate ?? 0),
            'share_amount' => $customer->referralShareAmount($amountPaise),
            'amount_source' => SettlementAmountSource::Manual,
            'entered_by' => $enteredBy->id,
            'finalized_at' => now(),
        ];

        // whereDate(), not updateOrCreate() with a raw date-string match key —
        // period_start is cast 'date' for reads but still persisted in the
        // model's full $dateFormat, so a plain where() equality on a
        // ->toDateString() string silently never matches the stored row (see
        // FinalizeReferralSettlements' own docblock for the same warning).
        $existing = ReferralSettlement::where('recurring_invoice_id', $template->id)
            ->whereDate('period_start', $monthStart)
            ->first();

        if ($existing) {
            $existing->update($values);

            return $existing;
        }

        return ReferralSettlement::create(['recurring_invoice_id' => $template->id, 'period_start' => $monthStart] + $values);
    }

    public function settle(ReferralSettlement $settlement, User $user): void
    {
        $settlement->update(['settled_at' => now(), 'settled_by' => $user->id]);
    }

    /**
     * Net settlement position across a partner's whole portfolio — positive
     * means NEDS owes the partner overall, negative means the partner owes
     * NEDS, both only counting rows not yet settled.
     *
     * @return array{neds_owes_partner: int, partner_owes_neds: int}
     */
    public function portfolioNetPosition(Collection $referralSettlements): array
    {
        $unsettled = $referralSettlements->whereNull('settled_at');

        return [
            'neds_owes_partner' => (int) $unsettled->where('flow_mode', PartnerCollectionMode::NedsCollects)->sum('share_amount'),
            'partner_owes_neds' => (int) $unsettled->where('flow_mode', PartnerCollectionMode::PartnerCollects)->sum('share_amount'),
        ];
    }
}
