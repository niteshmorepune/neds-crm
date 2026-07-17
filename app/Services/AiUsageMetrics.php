<?php

namespace App\Services;

use App\Models\AiUsage;
use Illuminate\Support\Carbon;

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
     *     by_feature: list<array{feature: string, label: string, calls: int, input_tokens: int, output_tokens: int, estimated_cost_paise: int}>,
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
                ];
            }

            $byFeature[$row->feature]['calls'] += (int) $row->calls;
            $byFeature[$row->feature]['input_tokens'] += (int) $row->input_tokens;
            $byFeature[$row->feature]['output_tokens'] += (int) $row->output_tokens;
            $byFeature[$row->feature]['estimated_cost_paise'] += $costPaise;
        }

        $byFeature = collect($byFeature)->sortByDesc('calls')->values()->all();

        return [
            'total_calls' => array_sum(array_column($byFeature, 'calls')),
            'total_input_tokens' => array_sum(array_column($byFeature, 'input_tokens')),
            'total_output_tokens' => array_sum(array_column($byFeature, 'output_tokens')),
            'estimated_cost_paise' => array_sum(array_column($byFeature, 'estimated_cost_paise')),
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
            'project_daily_update' => 'Project Daily Update Draft',
            'summarize_ticket' => 'Ticket Summary',
            'summarize_customer' => 'Client Summary',
            'team_performance_summary' => 'Team Performance Summary',
            'client_radar_suggestion' => 'Client Radar Suggestion',
            'monthly_wins_note' => 'Monthly Wins Note',
            default => ucwords(str_replace('_', ' ', $feature)),
        };
    }
}
