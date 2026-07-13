<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\RecurringInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregations for the "Business Overview" executive report. Kept separate from
 * ReportMetrics (which already covers Employee Performance / Revenue / Lead
 * Sources) since this report has a distinct shape: most of its sections are
 * point-in-time snapshots rather than period-based figures.
 */
class BusinessOverviewMetrics
{
    /**
     * How many months one billing cycle of a recurring template spans, for
     * normalizing to a monthly-equivalent MRR figure.
     */
    private const CYCLE_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'yearly' => 12,
    ];

    /**
     * Per partner: customers referred (via Customer.referring_partner_id) and
     * deals attributed to them (via Deal.partner_id) — two independent links,
     * kept separate rather than conflated. Snapshot as of now.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function partnerPerformance(): Collection
    {
        return Partner::query()->orderBy('name')->get()->map(function (Partner $partner) {
            $referred = $partner->referredCustomers()->get(['id', 'status']);
            $deals = Deal::query()->where('partner_id', $partner->id)->get(['stage', 'value']);

            $won = $deals->filter(fn (Deal $d) => $d->stage === DealStage::Won);
            $lost = $deals->filter(fn (Deal $d) => $d->stage === DealStage::Lost);
            $pipeline = $deals->reject(fn (Deal $d) => $d->stage->isTerminal());

            return [
                'partner' => $partner->name,
                'partner_id' => $partner->id,
                'customers_referred' => $referred->count(),
                'customers_active' => $referred->where('status', CustomerStatus::Active)->count(),
                'customers_inactive' => $referred->where('status', CustomerStatus::Inactive)->count(),
                'customers_prospect' => $referred->where('status', CustomerStatus::Prospect)->count(),
                'deals_won_count' => $won->count(),
                'deals_won_value' => (int) $won->sum('value'),
                'deals_pipeline_count' => $pipeline->count(),
                'deals_pipeline_value' => (int) $pipeline->sum('value'),
                'deals_lost_count' => $lost->count(),
                'deals_lost_value' => (int) $lost->sum('value'),
            ];
        })->reject(fn (array $row) => $row['customers_referred'] === 0
            && $row['deals_won_count'] === 0 && $row['deals_pipeline_count'] === 0 && $row['deals_lost_count'] === 0
        )->sortByDesc('deals_won_value')->values();
    }

    /**
     * Outstanding invoices bucketed by days overdue. Snapshot as of today —
     * ignores the report's FY picker on purpose (aging is inherently "as of
     * now", not a period figure).
     */
    public function arAging(): array
    {
        $today = Carbon::today();

        $bucketLabels = [
            'current' => 'Current (not yet due)',
            '0_30' => '0–30 days overdue',
            '31_60' => '31–60 days overdue',
            '61_90' => '61–90 days overdue',
            '90_plus' => '90+ days overdue',
        ];
        $totals = array_fill_keys(array_keys($bucketLabels), 0);

        $invoices = Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value, InvoiceStatus::Draft->value])
            ->with('customer')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balance() > 0)
            ->map(function (Invoice $invoice) use ($today, &$totals) {
                // Positive once $today is past due_date (i.e. overdue).
                $daysOverdue = (int) $invoice->due_date->copy()->startOfDay()->diffInDays($today, false);
                $bucket = match (true) {
                    $daysOverdue <= 0 => 'current',
                    $daysOverdue <= 30 => '0_30',
                    $daysOverdue <= 60 => '31_60',
                    $daysOverdue <= 90 => '61_90',
                    default => '90_plus',
                };
                $balance = $invoice->balance();
                $totals[$bucket] += $balance;

                return [
                    'customer' => $invoice->customer?->company_name ?? 'Unknown',
                    'invoice_number' => $invoice->invoice_number,
                    'due_date' => $invoice->due_date,
                    'days_overdue' => $daysOverdue,
                    'balance' => $balance,
                    'bucket' => $bucket,
                ];
            })
            ->sortByDesc('days_overdue')
            ->values()
            ->all();

        return [
            'total_outstanding' => array_sum($totals),
            'buckets' => collect($bucketLabels)->map(fn ($label, $key) => [
                'key' => $key, 'label' => $label, 'total' => $totals[$key],
            ])->values()->all(),
            'invoices' => $invoices,
        ];
    }

    /**
     * Active recurring templates normalized to a monthly-equivalent MRR figure,
     * grouped by service, plus contracts expiring within the next 30 days.
     * Snapshot as of now — ignores the FY picker on purpose.
     */
    public function mrrSnapshot(): array
    {
        $windowStart = now()->startOfDay();
        $windowEnd = now()->addDays(30)->endOfDay();

        $rows = RecurringInvoice::query()
            ->where('is_active', true)
            ->with(['items', 'service', 'customer'])
            ->get()
            ->map(function (RecurringInvoice $template) {
                // Same pre-GST formula as GenerateRecurringInvoices: sum of
                // quantity*rate per item, minus the template discount, floored at 0.
                $cycleAmount = (int) $template->items->sum(fn ($item) => (int) round(((float) $item->quantity) * (int) $item->rate));
                $cycleAmount = max(0, $cycleAmount - (int) $template->discount);
                $cycleMonths = self::CYCLE_MONTHS[$template->frequency->value];

                return [
                    'customer' => $template->customer?->company_name ?? 'Unknown',
                    'service' => $template->service?->name ?? 'Unspecified',
                    'frequency' => $template->frequency->label(),
                    'monthly_equivalent' => (int) round($cycleAmount / $cycleMonths),
                    'end_date' => $template->end_date,
                ];
            });

        $byService = $rows->groupBy('service')
            ->map(fn (Collection $group, string $name) => ['name' => $name, 'monthly_equivalent' => (int) $group->sum('monthly_equivalent')])
            ->sortByDesc('monthly_equivalent')
            ->values()
            ->all();

        $expiring = $rows
            ->filter(fn (array $r) => $r['end_date'] !== null && $r['end_date']->betweenIncluded($windowStart, $windowEnd))
            ->sortBy('end_date')
            ->values()
            ->all();

        return [
            'total_mrr' => (int) $rows->sum('monthly_equivalent'),
            'by_service' => $byService,
            'expiring_count' => count($expiring),
            'expiring' => $expiring,
        ];
    }

    /**
     * % of period revenue coming from the top 5 / top 10 clients. Pure
     * function over data the caller already has (ReportMetrics::revenue()'s
     * by_client, already sorted desc) — kept decoupled from ReportMetrics.
     *
     * @param  array<int, array{name: string, total: int}>  $byClient
     */
    public function clientConcentration(array $byClient, int $periodTotal): array
    {
        $top5Total = (int) collect($byClient)->take(5)->sum('total');
        $top10Total = (int) collect($byClient)->take(10)->sum('total');

        return [
            'top5_total' => $top5Total,
            'top10_total' => $top10Total,
            'top5_pct' => $periodTotal > 0 ? round($top5Total / $periodTotal * 100, 1) : 0.0,
            'top10_pct' => $periodTotal > 0 ? round($top10Total / $periodTotal * 100, 1) : 0.0,
            'clients' => $byClient,
        ];
    }

    /**
     * Company-wide pipeline (deliberately NOT Deal::visibleTo($user)-scoped,
     * unlike DashboardMetrics::salesStats() which is per-sales-rep) plus
     * win rate / avg deal size / avg sales-cycle length for deals that closed
     * (won or lost) within the given period.
     */
    public function pipelineFunnel(Carbon $from, Carbon $to): array
    {
        $openStages = array_values(array_filter(DealStage::cases(), fn (DealStage $s) => ! $s->isTerminal()));

        $counts = Deal::query()
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->selectRaw('stage, COUNT(*) as deals, COALESCE(SUM(value),0) as value')
            ->groupBy('stage')
            ->get()
            ->keyBy(fn ($row) => $row->stage instanceof DealStage ? $row->stage->value : $row->stage);

        $pipeline = collect($openStages)->map(function (DealStage $stage) use ($counts) {
            $row = $counts->get($stage->value);

            return ['stage' => $stage->label(), 'deals' => (int) ($row->deals ?? 0), 'value' => (int) ($row->value ?? 0)];
        })->values()->all();

        $won = Deal::query()
            ->where('stage', DealStage::Won->value)
            ->whereBetween('won_at', [$from, $to])
            ->get(['value', 'won_at', 'created_at']);

        // Deals have no `lost_at` column — the `saving` hook only ever stamps
        // `won_at` (nulling it on any non-Won stage). We approximate "lost
        // within the period" via `updated_at`, which IS touched by that same
        // hook when `stage` goes dirty. CAVEAT: editing a Lost deal again
        // afterward (a note, owner reassignment, etc.) moves `updated_at`
        // forward and will misattribute it to a later period than it was
        // actually lost in — accepted limitation absent a schema change.
        $lostCount = Deal::query()
            ->where('stage', DealStage::Lost->value)
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $wonCount = $won->count();
        $closedCount = $wonCount + $lostCount;

        return [
            'pipeline' => $pipeline,
            'open_deals' => array_sum(array_column($pipeline, 'deals')),
            'open_value' => array_sum(array_column($pipeline, 'value')),
            'won_count' => $wonCount,
            'lost_count' => $lostCount,
            'win_rate_pct' => $closedCount > 0 ? (int) round($wonCount / $closedCount * 100) : null,
            // Avg deal size / cycle length are Won-only (not blended with Lost) —
            // these read as "what's a typical closed-won deal worth / how long
            // does it take to close one", the conventional meaning of both KPIs.
            'avg_deal_size' => $wonCount > 0 ? (int) round($won->avg('value')) : null,
            'avg_sales_cycle_days' => $wonCount > 0
                ? (int) round($won->avg(fn (Deal $d) => $d->created_at->diffInDays($d->won_at)))
                : null,
        ];
    }
}
