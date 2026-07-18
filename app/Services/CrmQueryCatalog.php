<?php

namespace App\Services;

use App\Enums\CrmQueryType;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Turns one CrmQueryType into a bounded, pre-formatted list of {label,
 * value} rows — real numbers computed by the SAME metrics services every
 * other report page already uses, never anything the AI touches. This one
 * array is the single source of truth for both the "Ask the CRM" answer
 * panel's figures table AND the text fed to AiAssistant::narrateCrmAnswer()
 * — so what's shown on screen is exactly what the narration was grounded
 * in, never two different representations drifting apart.
 *
 * List-shaped results are capped (see self::ROW_CAP) to keep both the AI
 * prompt and the on-screen table short — "Open full report" links to the
 * real page for the complete picture.
 */
class CrmQueryCatalog
{
    private const ROW_CAP = 5;

    public function __construct(
        private readonly SalesPipelineMetrics $pipeline,
        private readonly ClientRadarService $radar,
        private readonly ReportMetrics $reports,
        private readonly BusinessOverviewMetrics $overview,
        private readonly AiUsageMetrics $aiUsage,
    ) {}

    /**
     * @return list<array{label: string, value: string}>
     */
    public function run(CrmQueryType $type, User $user): array
    {
        return match ($type) {
            CrmQueryType::SalesPipelineKpis => $this->salesPipelineKpis($user),
            CrmQueryType::ClientRadar => $this->clientRadar(),
            CrmQueryType::RevenueSummary => $this->revenueSummary(),
            CrmQueryType::ServiceBreakdown => $this->serviceBreakdown($user),
            CrmQueryType::LeadSourcePerformance => $this->leadSourcePerformance(),
            CrmQueryType::CashForecast => $this->cashForecast(),
            CrmQueryType::MrrSnapshot => $this->mrrSnapshot(),
            CrmQueryType::ArAging => $this->arAging(),
            CrmQueryType::RepLeaderboard => $this->repLeaderboard(),
            CrmQueryType::NeedsAttention => $this->needsAttention($user),
            CrmQueryType::AiUsageSummary => $this->aiUsageSummary(),
        };
    }

    private function salesPipelineKpis(User $user): array
    {
        $k = $this->pipeline->kpis($user);

        return [
            ['label' => 'Open pipeline', 'value' => Money::format($k['open_pipeline_value'])],
            ['label' => 'Weighted forecast', 'value' => Money::format($k['weighted_forecast'])],
            ['label' => 'Won this month', 'value' => Money::format($k['won_this_month_value'])],
            ['label' => 'Won this FY', 'value' => Money::format($k['won_this_fy_value'])],
            ['label' => 'Win rate', 'value' => $k['win_rate'] !== null ? "{$k['win_rate']}%" : 'not enough data yet'],
            ['label' => 'Avg deal size', 'value' => $k['avg_deal_size'] !== null ? Money::format($k['avg_deal_size']) : '—'],
            ['label' => 'Avg sales cycle', 'value' => $k['avg_sales_cycle_days'] !== null ? "{$k['avg_sales_cycle_days']} days" : '—'],
        ];
    }

    private function clientRadar(): array
    {
        $rows = $this->radar->flaggedClients();

        $lines = [['label' => 'Clients flagged', 'value' => (string) $rows->count()]];

        foreach ($rows->take(self::ROW_CAP) as $row) {
            $flagLabels = collect($row['flags'])->pluck('label')->implode(', ');
            $lines[] = ['label' => $row['customer']->company_name, 'value' => $flagLabels];
        }

        if ($rows->count() > self::ROW_CAP) {
            $lines[] = ['label' => '…and', 'value' => ($rows->count() - self::ROW_CAP).' more'];
        }

        return $lines;
    }

    private function revenueSummary(): array
    {
        [$from, $to] = $this->currentFinancialYear();
        $r = $this->reports->revenue($from, $to);

        $lines = [
            ['label' => 'Total revenue (this FY)', 'value' => Money::format($r['total'])],
            ['label' => 'Recurring', 'value' => Money::format($r['recurring'])],
            ['label' => 'One-time', 'value' => Money::format($r['one_time'])],
            ['label' => 'Invoices billed', 'value' => (string) $r['invoice_count']],
        ];

        foreach (array_slice($r['by_service'], 0, 3) as $s) {
            $lines[] = ['label' => 'Top service: '.$s['name'], 'value' => Money::format($s['total'])];
        }

        foreach (array_slice($r['by_client'], 0, 3) as $c) {
            $lines[] = ['label' => 'Top client: '.$c['name'], 'value' => Money::format($c['total'])];
        }

        return $lines;
    }

    private function serviceBreakdown(User $user): array
    {
        $rows = array_slice($this->pipeline->serviceBreakdown($user), 0, self::ROW_CAP);

        return array_map(fn ($r) => [
            'label' => $r['service'],
            'value' => sprintf(
                'open %s, won this month %s, win rate %s',
                Money::format($r['open_pipeline_value']),
                Money::format($r['won_this_month_value']),
                $r['win_rate'] !== null ? "{$r['win_rate']}%" : 'not enough data',
            ),
        ], $rows);
    }

    private function leadSourcePerformance(): array
    {
        $r = $this->reports->leadSourcePerformance(now()->startOfMonth(), now()->endOfMonth());

        $lines = [
            ['label' => 'Total leads this month', 'value' => (string) $r['total']],
            ['label' => 'Converted', 'value' => (string) $r['converted']],
            ['label' => 'Won value', 'value' => Money::format($r['won_value'])],
        ];

        foreach (array_slice($r['by_source'], 0, self::ROW_CAP) as $s) {
            $lines[] = [
                'label' => $s['label'],
                'value' => "{$s['total']} leads, {$s['conversion_rate']}% conversion, ".Money::format($s['won_value']).' won',
            ];
        }

        return $lines;
    }

    private function cashForecast(): array
    {
        $r = $this->overview->cashForecast();

        $lines = [['label' => 'Total forecast (next 3 months)', 'value' => Money::format($r['total_forecast'])]];

        foreach ($r['buckets'] as $b) {
            $lines[] = ['label' => $b['label'], 'value' => Money::format($b['total']).' (recurring '.Money::format($b['recurring_expected']).' + receivables '.Money::format($b['receivables_due']).')'];
        }

        return $lines;
    }

    private function mrrSnapshot(): array
    {
        $r = $this->overview->mrrSnapshot();

        $lines = [
            ['label' => 'Total MRR', 'value' => Money::format($r['total_mrr'])],
            ['label' => 'Contracts expiring within 30 days', 'value' => (string) $r['expiring_count']],
        ];

        foreach (array_slice($r['by_service'], 0, self::ROW_CAP) as $s) {
            $lines[] = ['label' => $s['name'], 'value' => Money::format($s['monthly_equivalent']).'/mo'];
        }

        return $lines;
    }

    private function arAging(): array
    {
        $r = $this->overview->arAging();

        $lines = [['label' => 'Total outstanding', 'value' => Money::format($r['total_outstanding'])]];

        foreach ($r['buckets'] as $b) {
            $lines[] = ['label' => $b['label'], 'value' => Money::format($b['total'])];
        }

        return $lines;
    }

    private function repLeaderboard(): array
    {
        $rows = collect($this->pipeline->repLeaderboard())
            ->sortByDesc('won_this_month_value')
            ->take(self::ROW_CAP);

        return $rows->map(fn ($r) => [
            'label' => $r['user']->name,
            'value' => sprintf(
                'won this month %s, pipeline %s, win rate %s%s',
                Money::format($r['won_this_month_value']),
                Money::format($r['pipeline_value']),
                $r['win_rate'] !== null ? "{$r['win_rate']}%" : 'not enough data',
                $r['target_pct'] !== null ? ", {$r['target_pct']}% of target" : '',
            ),
        ])->values()->all();
    }

    private function needsAttention(User $user): array
    {
        $r = $this->pipeline->needsAttention($user);

        $lines = [
            ['label' => 'Stale in current stage', 'value' => (string) $r['stale']->count()],
            ['label' => 'Overdue follow-ups', 'value' => (string) $r['overdue_followups']->count()],
            ['label' => 'Unowned', 'value' => (string) $r['unowned']->count()],
            ['label' => 'Zero value', 'value' => (string) $r['zero_value']->count()],
        ];

        foreach ($r['stale']->take(3) as $deal) {
            $lines[] = ['label' => 'Stale: '.$deal->title, 'value' => $deal->customer?->company_name ?? 'Unknown client'];
        }

        return $lines;
    }

    private function aiUsageSummary(): array
    {
        $r = $this->aiUsage->monthly(now()->startOfMonth(), now()->endOfMonth());

        $lines = [
            ['label' => 'AI calls this month', 'value' => (string) $r['total_calls']],
            ['label' => 'Estimated cost', 'value' => Money::format($r['estimated_cost_paise'])],
        ];

        foreach (array_slice($r['by_feature'], 0, self::ROW_CAP) as $f) {
            $lines[] = ['label' => $f['label'], 'value' => "{$f['calls']} calls, ".Money::format($f['estimated_cost_paise'])];
        }

        return $lines;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentFinancialYear(): array
    {
        $startYear = now()->month >= 4 ? now()->year : now()->year - 1;

        return [Carbon::create($startYear, 4, 1)->startOfDay(), Carbon::create($startYear + 1, 3, 31)->endOfDay()];
    }
}
