<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\WeeklyOwnerDigest;
use App\Models\User;
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
 * nothing new computed here.
 */
class SendWeeklyOwnerDigest extends Command
{
    protected $signature = 'app:send-weekly-owner-digest';

    protected $description = 'Email Admin/Manager an AI synthesis of the week ahead (run Monday 09:00 IST).';

    public function handle(BusinessOverviewMetrics $business, ClientRadarService $radar, AiAssistant $ai): int
    {
        // Unlike the daily digest, this has no non-AI value of its own — the
        // owner can already see each underlying report directly. Skip the
        // whole thing rather than send an empty-of-substance email.
        if (! Ai::enabled()) {
            $this->info('AI disabled — nothing to synthesize, skipping.');

            return self::SUCCESS;
        }

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

        $summary = $ai->summarizeWeeklyOwnerDigest($lines);

        if ($summary === null) {
            $this->info('AI returned nothing — skipping.');

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
