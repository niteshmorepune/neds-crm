<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\WeeklyOwnerDigest;
use App\Models\User;
use App\Models\WeeklyDigest;
use App\Services\AiAssistant;
use App\Services\BusinessOverviewMetrics;
use App\Services\ClientRadarService;
use App\Support\Ai;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Monday-morning AI synthesis of pipeline, cash position, and at-risk
 * clients for Admin/Manager — reuses BusinessOverviewMetrics and
 * ClientRadarService (the same services behind those reports' own pages),
 * nothing new computed here. Always records a WeeklyDigest history row for
 * trend tracking, even when AI is disabled/unavailable (summary stays
 * null) — the metrics themselves don't depend on AI, only the narrative
 * does, and the email is skipped in that case since it has no non-AI value
 * of its own (the owner can already see each underlying report directly).
 */
class SendWeeklyOwnerDigest extends Command
{
    protected $signature = 'app:send-weekly-owner-digest';

    protected $description = 'Email Admin/Manager an AI synthesis of the week ahead (run Monday 09:00 IST).';

    public function handle(BusinessOverviewMetrics $business, ClientRadarService $radar, AiAssistant $ai): int
    {
        $today = Carbon::today(config('app.display_timezone'));

        $pipeline = $business->pipelineFunnel($today->copy()->subDays(7), $today);
        $mrr = $business->mrrSnapshot();
        $cashForecast = $business->cashForecast();
        $arAging = $business->arAging();
        $flagged = $radar->flaggedClients();

        $flagCounts = [];
        foreach ($flagged as $row) {
            foreach (array_keys($row['flags']) as $flagKey) {
                $flagCounts[$flagKey] = ($flagCounts[$flagKey] ?? 0) + 1;
            }
        }

        $ninetyPlusOverdue = collect($arAging['buckets'])->firstWhere('key', '90_plus')['total'] ?? 0;
        $thisMonthCash = $cashForecast['buckets'][0]['total'] ?? 0;

        $lines = [
            'Open pipeline: '.$pipeline['open_deals'].' deals worth '.Money::format($pipeline['open_value']),
            'Deals won in the last 7 days: '.$pipeline['won_count'],
            'Deals lost in the last 7 days: '.$pipeline['lost_count'],
            'Monthly recurring revenue: '.Money::format($mrr['total_mrr']),
            'Contracts expiring in the next 30 days: '.$mrr['expiring_count'],
            'Cash expected this month: '.Money::format($thisMonthCash),
            'Cash expected over the next 3 months: '.Money::format($cashForecast['total_forecast']),
            'Total receivables outstanding: '.Money::format($arAging['total_outstanding']),
            'Receivables 90+ days overdue: '.Money::format($ninetyPlusOverdue),
            'Clients flagged by Client Radar: '.$flagged->count(),
            'Clients with a low-satisfaction flag: '.($flagCounts['low_satisfaction'] ?? 0),
            'Clients with an overdue-invoice flag: '.($flagCounts['overdue_invoice'] ?? 0),
        ];

        $summary = Ai::enabled() ? $ai->summarizeWeeklyOwnerDigest($lines) : null;

        $metrics = [
            'pipeline_open_deals_count' => $pipeline['open_deals'],
            'pipeline_open_value' => $pipeline['open_value'],
            'deals_won_count' => $pipeline['won_count'],
            'deals_lost_count' => $pipeline['lost_count'],
            'mrr_total' => $mrr['total_mrr'],
            'recurring_contracts_expiring_count' => $mrr['expiring_count'],
            'cash_expected_this_month' => $thisMonthCash,
            'cash_expected_three_months' => $cashForecast['total_forecast'],
            'receivables_total_outstanding' => $arAging['total_outstanding'],
            'receivables_overdue_ninety_plus_days' => $ninetyPlusOverdue,
            'client_radar_flagged_count' => $flagged->count(),
            'client_radar_low_satisfaction_count' => $flagCounts['low_satisfaction'] ?? 0,
            'client_radar_overdue_invoice_count' => $flagCounts['overdue_invoice'] ?? 0,
        ];

        if ($summary !== null) {
            $metrics['summary'] = $summary;
        }

        // Idempotent against a manual re-run on the same Monday (never
        // overwrites an already-recorded summary with a null one from a
        // second, AI-less run). Deliberately whereDate() rather than
        // updateOrCreate(['digest_date' => ...]) — the `date` cast writes
        // digest_date using the connection's full datetime format
        // (Model::getDateFormat() ignores the ":Y-m-d" cast parameter for
        // storage, only Carbon::toDateString() is plain Y-m-d), so a raw
        // string-equality lookup silently misses the existing row and hits
        // the unique constraint instead of updating it.
        $existing = WeeklyDigest::query()->whereDate('digest_date', $today)->first();

        if ($existing) {
            $existing->update($metrics);
        } else {
            WeeklyDigest::query()->create(['digest_date' => $today->toDateString(), ...$metrics]);
        }

        if ($summary === null) {
            $this->info('AI unavailable — metrics snapshot recorded, skipping email.');

            return self::SUCCESS;
        }

        $recipients = User::query()->where('is_active', true)->withAnyRole(UserRole::Admin, UserRole::Manager)->get();

        foreach ($recipients as $user) {
            // saveQuietly: no activity-log entry, no re-dispatch loop — same
            // pattern as the daily digest's ai_daily_digest caching.
            $user->forceFill([
                'ai_weekly_digest' => $summary,
                'ai_weekly_digest_date' => $today->toDateString(),
            ])->saveQuietly();

            Mail::to($user)->send(new WeeklyOwnerDigest($user, $today, $summary));
        }

        $this->info("Sent weekly owner digest to {$recipients->count()} recipient(s).");

        return self::SUCCESS;
    }
}
