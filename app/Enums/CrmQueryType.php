<?php

namespace App\Enums;

/**
 * The bounded catalog of business questions "Ask the CRM" can answer.
 * Each case maps 1:1 to an existing metrics-service method via
 * App\Services\CrmQueryCatalog — the AI's only job is picking one of
 * these (or "unsupported"), never fetching or inventing numbers itself.
 */
enum CrmQueryType: string
{
    case SalesPipelineKpis = 'sales_pipeline_kpis';
    case ClientRadar = 'client_radar';
    case RevenueSummary = 'revenue_summary';
    case ServiceBreakdown = 'service_breakdown';
    case LeadSourcePerformance = 'lead_source_performance';
    case CashForecast = 'cash_forecast';
    case MrrSnapshot = 'mrr_snapshot';
    case ArAging = 'ar_aging';
    case RepLeaderboard = 'rep_leaderboard';
    case NeedsAttention = 'needs_attention';
    case AiUsageSummary = 'ai_usage_summary';

    public function label(): string
    {
        return match ($this) {
            self::SalesPipelineKpis => 'Sales Pipeline KPIs',
            self::ClientRadar => 'Client Radar',
            self::RevenueSummary => 'Revenue Summary',
            self::ServiceBreakdown => 'Service Breakdown',
            self::LeadSourcePerformance => 'Lead Source Performance',
            self::CashForecast => 'Cash Forecast',
            self::MrrSnapshot => 'MRR Snapshot',
            self::ArAging => 'AR Aging',
            self::RepLeaderboard => 'Rep Leaderboard',
            self::NeedsAttention => 'Needs Attention',
            self::AiUsageSummary => 'AI Usage Summary',
        };
    }

    /**
     * One line per type — used both in the classifier's system prompt
     * (so it knows what each type covers) and in the "here's what I can
     * answer" list shown when a question doesn't match anything.
     */
    public function description(): string
    {
        return match ($this) {
            self::SalesPipelineKpis => 'open pipeline value, weighted forecast, won-this-month/FY, win rate, avg deal size, avg sales cycle',
            self::ClientRadar => 'which active clients are flagged for a check-in (no contact, declining activity, overdue invoice, low satisfaction, upsell opportunity)',
            self::RevenueSummary => 'total revenue this financial year, split recurring vs one-time, by service, by client',
            self::ServiceBreakdown => 'pipeline value, won-this-month value, and win rate per service line',
            self::LeadSourcePerformance => 'leads captured, conversion rate, and won value by acquisition source or campaign this month',
            self::CashForecast => 'expected cash inflow over the next 3 months (recurring billing + receivables due)',
            self::MrrSnapshot => 'total monthly recurring revenue, by service, and contracts expiring within 30 days',
            self::ArAging => 'total amount owed by clients, bucketed by how overdue it is',
            self::RepLeaderboard => 'per-sales-rep pipeline, won-this-month value, target progress, and win rate',
            self::NeedsAttention => 'open deals that need a look: stale in stage, overdue follow-up, unowned, or zero value',
            self::AiUsageSummary => 'how many AI calls were made this month, by feature, and the estimated cost',
        };
    }

    /**
     * @return array{name: string, label: string}
     */
    public function reportRoute(): array
    {
        return match ($this) {
            self::SalesPipelineKpis, self::ServiceBreakdown, self::RepLeaderboard, self::NeedsAttention => ['name' => 'sales-dashboard.index', 'label' => 'Sales Dashboard'],
            self::ClientRadar => ['name' => 'client-radar.index', 'label' => 'Client Radar'],
            self::RevenueSummary => ['name' => 'reports.revenue', 'label' => 'Revenue Report'],
            self::LeadSourcePerformance => ['name' => 'reports.lead-sources', 'label' => 'Lead Source Performance'],
            self::CashForecast => ['name' => 'reports.cash-forecast', 'label' => 'Cash Forecast'],
            self::MrrSnapshot, self::ArAging => ['name' => 'reports.business-overview', 'label' => 'Business Overview'],
            self::AiUsageSummary => ['name' => 'reports.ai-usage', 'label' => 'AI Usage Report'],
        };
    }
}
