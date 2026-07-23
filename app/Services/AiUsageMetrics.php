<?php

namespace App\Services;

use App\Models\AiUsage;
use App\Models\AiUsageSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns raw ai_usages rows (feature, model, token counts) into a monthly
 * usage-and-estimated-cost report. Cost is computed from config('services.
 * anthropic.pricing') — an estimate only, never a real financial figure.
 */
class AiUsageMetrics
{
    /**
     * @return array{
     *     total_calls: int,
     *     total_input_tokens: int,
     *     total_output_tokens: int,
     *     estimated_cost_paise: int,
     *     total_feedback_up: int,
     *     total_feedback_down: int,
     *     by_feature: list<array{feature: string, label: string, calls: int, input_tokens: int, output_tokens: int, estimated_cost_paise: int, feedback_up: int, feedback_down: int}>,
     * }
     */
    public function monthly(Carbon $from, Carbon $to): array
    {
        // Grouped by feature+model first so a model change mid-month still
        // prices each row's tokens at the rate that actually applied.
        $groups = AiUsage::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('feature, model, count(*) as calls, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens')
            ->groupBy('feature', 'model')
            ->get();

        $byFeature = [];

        foreach ($groups as $row) {
            $costPaise = $this->costPaise((string) $row->model, (int) $row->input_tokens, (int) $row->output_tokens);

            if (! isset($byFeature[$row->feature])) {
                $byFeature[$row->feature] = [
                    'feature' => $row->feature,
                    'label' => $this->label($row->feature),
                    'calls' => 0,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'estimated_cost_paise' => 0,
                    'feedback_up' => 0,
                    'feedback_down' => 0,
                ];
            }

            $byFeature[$row->feature]['calls'] += (int) $row->calls;
            $byFeature[$row->feature]['input_tokens'] += (int) $row->input_tokens;
            $byFeature[$row->feature]['output_tokens'] += (int) $row->output_tokens;
            $byFeature[$row->feature]['estimated_cost_paise'] += $costPaise;
        }

        // One optional click after someone's actually looked at a draft/answer
        // (RatesAiDrafts) — a real quality signal per feature, not just a call
        // count. Most calls will have no feedback at all; that's expected.
        $feedbackCounts = AiUsage::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('feedback')
            ->selectRaw('feature, feedback, count(*) as total')
            ->groupBy('feature', 'feedback')
            ->get();

        foreach ($feedbackCounts as $row) {
            if (! isset($byFeature[$row->feature])) {
                continue;
            }

            if ($row->feedback === 'up') {
                $byFeature[$row->feature]['feedback_up'] += (int) $row->total;
            } elseif ($row->feedback === 'down') {
                $byFeature[$row->feature]['feedback_down'] += (int) $row->total;
            }
        }

        $byFeature = collect($byFeature)->sortByDesc('calls')->values()->all();

        return [
            'total_calls' => array_sum(array_column($byFeature, 'calls')),
            'total_input_tokens' => array_sum(array_column($byFeature, 'input_tokens')),
            'total_output_tokens' => array_sum(array_column($byFeature, 'output_tokens')),
            'estimated_cost_paise' => array_sum(array_column($byFeature, 'estimated_cost_paise')),
            'total_feedback_up' => array_sum(array_column($byFeature, 'feedback_up')),
            'total_feedback_down' => array_sum(array_column($byFeature, 'feedback_down')),
            'by_feature' => $byFeature,
        ];
    }

    private function costPaise(string $model, int $inputTokens, int $outputTokens): int
    {
        $rates = config("services.anthropic.pricing.{$model}") ?? config('services.anthropic.pricing.default');
        $usdToInr = (float) config('services.anthropic.usd_to_inr');

        $costUsd = ($inputTokens / 1_000_000) * $rates['input']
            + ($outputTokens / 1_000_000) * $rates['output'];

        return (int) round($costUsd * $usdToInr * 100);
    }

    /**
     * Real AI usage from Drishti's own AIUsageLog (X-Service-Key,
     * GET /api/ai/usage) for the same period, folded into the combined
     * cross-app total on the AI Usage Report. Null — never an exception —
     * when Drishti isn't configured or the call fails, same "degrade to
     * nothing, never block the page" treatment as DraftMonthlyWinsNote's
     * drishtiWinsFor().
     *
     * @return array{calls: int, input_tokens: int, output_tokens: int, estimated_cost_paise: int}|null
     */
    public function drishtiUsage(Carbon $from, Carbon $to): ?array
    {
        return $this->fetchAppUsage('Drishti', 'services.drishti.base_url', 'services.drishti.service_key', $from, $to);
    }

    /**
     * Same as drishtiUsage(), but for SMDost's own AIUsageLog. Both apps
     * expose the identical GET /api/ai/usage shape (SMDost's mirrors
     * Drishti's), so this and drishtiUsage() share fetchAppUsage() below.
     *
     * @return array{calls: int, input_tokens: int, output_tokens: int, estimated_cost_paise: int}|null
     */
    public function smdostUsage(Carbon $from, Carbon $to): ?array
    {
        return $this->fetchAppUsage('SMDost', 'services.smdost.base_url', 'services.smdost.service_key', $from, $to);
    }

    /**
     * @return array{calls: int, input_tokens: int, output_tokens: int, estimated_cost_paise: int}|null
     */
    private function fetchAppUsage(string $appName, string $baseUrlConfigKey, string $serviceKeyConfigKey, Carbon $from, Carbon $to): ?array
    {
        $baseUrl = rtrim((string) config($baseUrlConfigKey), '/');
        $serviceKey = (string) config($serviceKeyConfigKey);

        if (! $baseUrl || ! $serviceKey) {
            return null;
        }

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->get("{$baseUrl}/api/ai/usage", [
                    'from' => $from->copy()->startOfDay()->toIso8601String(),
                    'to' => $to->copy()->endOfDay()->toIso8601String(),
                ]);

            if (! $response->successful()) {
                Log::warning("{$appName} AI usage fetch failed", ['status' => $response->status()]);

                return null;
            }

            $totals = $response->json('data.totals') ?? [];
            $costUsd = (float) ($totals['_sum']['costUsd'] ?? 0);

            return [
                'calls' => (int) ($totals['_count'] ?? 0),
                'input_tokens' => (int) ($totals['_sum']['inputTokens'] ?? 0),
                'output_tokens' => (int) ($totals['_sum']['outputTokens'] ?? 0),
                'estimated_cost_paise' => (int) round($costUsd * (float) config('services.anthropic.usd_to_inr') * 100),
            ];
        } catch (\Throwable $e) {
            Log::warning("{$appName} AI usage exception", ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Combined CRM + Drishti + SMDost estimated spend against the
     * admin-configured monthly budget ceiling (AiUsageSetting) — a
     * self-tracked stand-in for a real vendor "credit balance", since
     * Anthropic doesn't expose one via API. Null budget (0, the default)
     * means no ceiling is set yet.
     *
     * @return array{combined_cost_paise: int, budget_paise: int, pct: int|null}
     */
    public function budgetStatus(int $crmCostPaise, ?int $drishtiCostPaise, ?int $smdostCostPaise = null): array
    {
        $combined = $crmCostPaise + ($drishtiCostPaise ?? 0) + ($smdostCostPaise ?? 0);
        $budget = AiUsageSetting::current()->monthly_budget_paise;

        return [
            'combined_cost_paise' => $combined,
            'budget_paise' => $budget,
            'pct' => $budget > 0 ? (int) round($combined / $budget * 100) : null,
        ];
    }

    /**
     * Human-readable label for a `feature` identifier recorded on ai_usages.
     * Falls back to a title-cased version of the raw key for any feature
     * added later without a matching entry here.
     */
    private function label(string $feature): string
    {
        return match ($feature) {
            'lead_scoring' => 'Lead Scoring',
            'draft_ticket_reply' => 'Draft Ticket Reply',
            'draft_lead_followup' => 'Draft Lead Follow-up',
            'draft_lead_nurture_followup' => 'Lead Nurture Follow-up',
            'draft_festival_greeting' => 'Festival Greeting Draft',
            'daily_priorities_summary' => 'Morning Digest Summary',
            'weekly_owner_digest' => 'Weekly Owner Digest',
            'project_daily_update' => 'Project Daily Update Draft',
            'summarize_ticket' => 'Ticket Summary',
            'summarize_customer' => 'Client Summary',
            'team_performance_summary' => 'Team Performance Summary',
            'client_radar_suggestion' => 'Client Radar Suggestion',
            'monthly_wins_note' => 'Monthly Wins Note',
            'portal_assistant_answer' => 'Portal Assistant Answer',
            'csat_recovery_message' => 'CSAT Recovery Message',
            'ticket_triage_suggestion' => 'Ticket Triage Suggestion',
            'onboarding_task_suggestion' => 'Onboarding Task Suggestion',
            'quotation_line_item_suggestion' => 'Quotation Line Item Suggestion',
            'crm_query_classify' => 'Ask the CRM (classify)',
            'crm_query_answer' => 'Ask the CRM (answer)',
            default => ucwords(str_replace('_', ' ', $feature)),
        };
    }
}
