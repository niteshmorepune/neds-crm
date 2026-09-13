<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\SystemAlert;
use App\Models\User;
use App\Notifications\OfferFunnelFailureSpikeNotification;
use App\Support\LogFailureScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * First-pass, deliberately narrow alerting for exactly two known failure
 * shapes surfaced by the 2026-09-13 offer-funnel investigation — NOT a
 * general failed-job monitor. Before this command, a systemic failure like
 * the wadesk.in template-sync outage (45 failed sends in one day) or the
 * bilingual-variable-count bug sat completely silent until someone happened
 * to grep the logs; this closes that specific gap for these two shapes only.
 *
 * 1. A spike in SendOfferRecommendationReadyJob's own "wadesk.in returned
 *    non-2xx" warning within the last hour (threshold configurable,
 *    defaults to 5) — the exact log line the outage above produced 45 times
 *    in one day with zero other trace anywhere (no failed_jobs row, since
 *    that job deliberately swallows the failure — see its own docblock).
 * 2. ANY "Razorpay order creation exception" in the last hour (threshold 1
 *    — payment failures are currently near-zero volume, so even one is
 *    worth a look, framed as lower urgency than the send-failure alert).
 *
 * Both log lines are read via LogFailureScanner rather than by touching
 * SendOfferRecommendationReadyJob or RazorpayClient to add a structured
 * counter — both are explicitly out of scope to modify for this task.
 *
 * Cooldown: once either check fires, a SystemAlert row records when, and
 * that same check won't fire again for ALERT_COOLDOWN_HOURS even if the
 * spike is still ongoing — one alert per incident, not one per hourly run.
 */
class MonitorOfferFunnelFailures extends Command
{
    protected $signature = 'app:monitor-offer-funnel-failures';

    protected $description = 'Alerts Admin/Manager on a spike in offer-recommendation WhatsApp send failures, or any Razorpay order-creation exception (run hourly).';

    private const SEND_FAILURE_ALERT_KEY = 'offer_recommendation_send_failure_spike';

    private const SEND_FAILURE_NEEDLE = 'SendOfferRecommendationReadyJob: wadesk.in returned non-2xx';

    private const SEND_FAILURE_THRESHOLD = 5;

    private const RAZORPAY_ALERT_KEY = 'razorpay_order_creation_exception';

    private const RAZORPAY_NEEDLE = 'Razorpay order creation exception';

    private const RAZORPAY_THRESHOLD = 1;

    private const ALERT_COOLDOWN_HOURS = 6;

    private const SCAN_WINDOW_HOURS = 1;

    public function handle(LogFailureScanner $scanner): int
    {
        $fired = 0;

        if ($this->checkAndAlert(
            $scanner,
            self::SEND_FAILURE_ALERT_KEY,
            self::SEND_FAILURE_NEEDLE,
            self::SEND_FAILURE_THRESHOLD,
            fn (int $count, string $topReason) => "Offer-recommendation WhatsApp sends are failing: {$count} \"wadesk.in returned non-2xx\" errors from SendOfferRecommendationReadyJob in the last hour".($topReason !== '' ? " — most common: \"{$topReason}\"." : '.'),
        )) {
            $fired++;
        }

        if ($this->checkAndAlert(
            $scanner,
            self::RAZORPAY_ALERT_KEY,
            self::RAZORPAY_NEEDLE,
            self::RAZORPAY_THRESHOLD,
            fn (int $count, string $topReason) => "Razorpay order creation is throwing exceptions: {$count} in the last hour".($topReason !== '' ? " — most common: \"{$topReason}\"." : '.').' Payment-start volume is currently low, so this is worth a look but not urgent.',
        )) {
            $fired++;
        }

        $this->info($fired > 0 ? "Fired {$fired} alert(s)." : 'No new alerts (no spike, or already in cooldown).');

        return self::SUCCESS;
    }

    private function checkAndAlert(LogFailureScanner $scanner, string $alertKey, string $needle, int $threshold, \Closure $summaryFor): bool
    {
        if (! $this->outOfCooldown($alertKey)) {
            return false;
        }

        $result = $scanner->countSince($needle, Carbon::now()->subHours(self::SCAN_WINDOW_HOURS));

        if ($result['count'] < $threshold) {
            return false;
        }

        $topReason = array_key_first($result['reasons']) ?? '';
        $summary = $summaryFor($result['count'], $topReason);

        $this->alertManagers(new OfferFunnelFailureSpikeNotification($summary, $result['reasons']));
        $this->markAlerted($alertKey);
        $this->info($summary);

        return true;
    }

    private function outOfCooldown(string $alertKey): bool
    {
        $alert = SystemAlert::where('alert_key', $alertKey)->first();

        return $alert === null || $alert->last_alerted_at->lte(Carbon::now()->subHours(self::ALERT_COOLDOWN_HOURS));
    }

    private function markAlerted(string $alertKey): void
    {
        SystemAlert::updateOrCreate(['alert_key' => $alertKey], ['last_alerted_at' => Carbon::now()]);
    }

    private function alertManagers(OfferFunnelFailureSpikeNotification $notification): void
    {
        User::where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get()
            ->each(fn (User $manager) => $manager->notify($notification));
    }
}
