<?php

namespace App\Services;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTouchChannel;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Powers the Visibility Audit recovery queue — Leads attributably stuck at
 * one of the funnel's tracked stages (see VisibilityAuditFunnelTrackingController)
 * with no completed purchase yet — and funnelSummary(), the full-funnel
 * count-per-stage view. "Filled the Meta lead form but never clicked
 * through at all" (previously not coverable — see LeadObserver's own note)
 * is now identified via `meta_leadgen_id IS NOT NULL AND service_id = GMB`,
 * the same cohort SendVisibilityAuditFirstInviteJob targets — NOT
 * `source = MetaAds`, which a real lead (id 225) proved unreliable: Meta's
 * own auto-sent WhatsApp message can beat the Lead Ads webhook to the CRM,
 * landing the lead as source=whatsapp even though it genuinely submitted
 * the Meta form (meta_leadgen_id gets backfilled either way).
 */
class VisibilityAuditFunnelMetrics
{
    /**
     * Leads who reached the landing page (an attributed `landing_viewed`
     * event) but never reached checkout and never paid.
     */
    public function stuckAtLanding(): Collection
    {
        return $this->stuckQuery(VisibilityAuditFunnelEventType::LandingViewed)
            ->whereDoesntHave('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed))
            ->get();
    }

    /**
     * Leads who reached checkout (an attributed `payment_viewed` event) but
     * never completed a payment.
     */
    public function stuckAtCheckout(): Collection
    {
        return $this->stuckQuery(VisibilityAuditFunnelEventType::PaymentViewed)->get();
    }

    /**
     * stuckAtLanding(), narrowed to Leads whose latest landing_viewed event
     * hasn't been nudged yet and is older than $olderThan — used by
     * SendVisibilityAuditRecoveryNudges so a lead is only ever nudged once
     * per visit, not once per scheduler run.
     */
    public function pendingLandingNudges(Carbon $olderThan): Collection
    {
        return $this->stuckAtLanding()->filter(function (Lead $lead) use ($olderThan) {
            $latest = $lead->visibilityAuditFunnelEvents->first();

            return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan);
        })->values();
    }

    /**
     * stuckAtCheckout(), same narrowing as pendingLandingNudges() above.
     */
    public function pendingCheckoutNudges(Carbon $olderThan): Collection
    {
        return $this->stuckAtCheckout()->filter(function (Lead $lead) use ($olderThan) {
            $latest = $lead->visibilityAuditFunnelEvents->first();

            return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan);
        })->values();
    }

    private function stuckQuery(VisibilityAuditFunnelEventType $type)
    {
        return Lead::query()
            ->whereHas('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', $type))
            ->whereDoesntHave('visibilityAuditPurchases')
            ->with(['owner', 'visibilityAuditFunnelEvents' => fn ($q) => $q->where('event_type', $type)->latest()]);
    }

    /**
     * Stage-by-stage counts for the GMB-tagged Meta Ads lead cohort: how
     * many were eligible, invited, reached the landing page, reached
     * checkout, and paid — in that order, each stage a subset of the one
     * before it in spirit (though not strictly a strict funnel query, since
     * a lead could in principle reach a later stage via an older recovery
     * link without a fresh landing_viewed event — rare enough not to chase).
     * $from/$to bound eligibility by Lead.created_at; omit both for
     * all-time totals. The `*_pct` keys are stage-to-stage conversion
     * (each vs. the stage immediately before it) plus one overall
     * paid/eligible figure — null (not 0) whenever the prior stage's count
     * is 0, so the dashboard can render "—" instead of a misleading 0%.
     *
     * @return array{eligible: int, invited: int, landing_viewed: int, checkout_viewed: int, paid: int, invited_pct: ?float, landing_pct: ?float, checkout_pct: ?float, paid_pct: ?float, overall_pct: ?float}
     */
    public function funnelSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $eligibleIds = $this->eligibleLeadsQuery($from, $to)->pluck('id');

        $summary = [
            'eligible' => $eligibleIds->count(),
            'invited' => $this->eligibleLeadsQuery($from, $to)->whereNotNull('visibility_audit_invited_at')->count(),
            'landing_viewed' => VisibilityAuditFunnelEvent::where('event_type', VisibilityAuditFunnelEventType::LandingViewed)
                ->whereIn('lead_id', $eligibleIds)
                ->distinct('lead_id')
                ->count('lead_id'),
            'checkout_viewed' => VisibilityAuditFunnelEvent::where('event_type', VisibilityAuditFunnelEventType::PaymentViewed)
                ->whereIn('lead_id', $eligibleIds)
                ->distinct('lead_id')
                ->count('lead_id'),
            'paid' => VisibilityAuditPurchase::whereIn('lead_id', $eligibleIds)->count(),
        ];

        $summary['invited_pct'] = $this->pct($summary['invited'], $summary['eligible']);
        $summary['landing_pct'] = $this->pct($summary['landing_viewed'], $summary['invited']);
        $summary['checkout_pct'] = $this->pct($summary['checkout_viewed'], $summary['landing_viewed']);
        $summary['paid_pct'] = $this->pct($summary['paid'], $summary['checkout_viewed']);
        $summary['overall_pct'] = $this->pct($summary['paid'], $summary['eligible']);

        return $summary;
    }

    /**
     * Daily counts per stage across the range, for the dashboard's trend
     * chart — bucketed by the Asia/Kolkata calendar date of each row (never
     * UTC; a raw day-boundary bucket would mis-bucket anything in the
     * ~5.5-hour UTC/IST gap, the same class of bug already caught once in
     * this app — see CLAUDE.md's Create Meeting timezone entry). $from/$to
     * are UTC instants (already display-timezone-resolved by the caller).
     *
     * @return list<array{label: string, eligible: int, invited: int, landing_viewed: int, checkout_viewed: int, paid: int}>
     */
    public function trend(Carbon $from, Carbon $to): array
    {
        $tz = config('app.display_timezone', 'Asia/Kolkata');
        $bucket = fn (Collection $rows, string $column) => $rows->groupBy(
            fn ($row) => $row->{$column}->timezone($tz)->toDateString()
        )->map->count();

        $eligibleByDay = $bucket($this->eligibleLeadsQuery($from, $to)->get(['created_at']), 'created_at');
        $invitedByDay = $bucket(
            Lead::whereNotNull('visibility_audit_invited_at')
                ->whereBetween('visibility_audit_invited_at', [$from, $to])
                ->get(['visibility_audit_invited_at']),
            'visibility_audit_invited_at'
        );
        $landingByDay = $bucket(
            VisibilityAuditFunnelEvent::where('event_type', VisibilityAuditFunnelEventType::LandingViewed)
                ->whereBetween('created_at', [$from, $to])->get(['created_at']),
            'created_at'
        );
        $checkoutByDay = $bucket(
            VisibilityAuditFunnelEvent::where('event_type', VisibilityAuditFunnelEventType::PaymentViewed)
                ->whereBetween('created_at', [$from, $to])->get(['created_at']),
            'created_at'
        );
        $paidByDay = $bucket(VisibilityAuditPurchase::whereBetween('created_at', [$from, $to])->get(['created_at']), 'created_at');

        $trend = [];
        foreach (CarbonPeriod::create($from->copy()->timezone($tz)->startOfDay(), '1 day', $to->copy()->timezone($tz)->startOfDay()) as $day) {
            $key = $day->toDateString();
            $trend[] = [
                'label' => $day->format('d M'),
                'eligible' => (int) ($eligibleByDay[$key] ?? 0),
                'invited' => (int) ($invitedByDay[$key] ?? 0),
                'landing_viewed' => (int) ($landingByDay[$key] ?? 0),
                'checkout_viewed' => (int) ($checkoutByDay[$key] ?? 0),
                'paid' => (int) ($paidByDay[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * How many touches each channel made in the window — the raw activity
     * count behind conversionByChannel()'s "does it actually convert" question.
     *
     * @return array{ai_whatsapp: int, staff_call: int}
     */
    public function touchesByChannel(?Carbon $from = null, ?Carbon $to = null): array
    {
        $counts = VisibilityAuditTouch::query()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->selectRaw('channel, count(*) as c')
            ->groupBy('channel')
            ->pluck('c', 'channel');

        return [
            'ai_whatsapp' => (int) ($counts[VisibilityAuditTouchChannel::AiWhatsapp->value] ?? 0),
            'staff_call' => (int) ($counts[VisibilityAuditTouchChannel::StaffCall->value] ?? 0),
        ];
    }

    /**
     * Of the leads who paid in the window, how many had at least one
     * staff_call touch logged before their purchase vs. AI-only — the
     * headline "does human follow-up move the needle" figure. Purchases
     * with no matched lead_id (an anonymous checkout, never clicked through
     * a tracked/attributed link) can't be attributed to either channel and
     * are excluded, same as every other lead-attributed metric in this class.
     *
     * @return array{staff_assisted: int, ai_only: int, total: int, staff_assisted_pct: ?float}
     */
    public function conversionByChannel(?Carbon $from = null, ?Carbon $to = null): array
    {
        $purchases = VisibilityAuditPurchase::query()
            ->whereNotNull('lead_id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->get(['lead_id', 'created_at']);

        $staffAssisted = 0;
        $aiOnly = 0;

        foreach ($purchases as $purchase) {
            $hadStaffTouch = VisibilityAuditTouch::where('lead_id', $purchase->lead_id)
                ->where('channel', VisibilityAuditTouchChannel::StaffCall)
                ->where('occurred_at', '<=', $purchase->created_at)
                ->exists();

            $hadStaffTouch ? $staffAssisted++ : $aiOnly++;
        }

        $total = $staffAssisted + $aiOnly;

        return [
            'staff_assisted' => $staffAssisted,
            'ai_only' => $aiOnly,
            'total' => $total,
            'staff_assisted_pct' => $this->pct($staffAssisted, $total),
        ];
    }

    /**
     * Meta leads that can never become VA-eligible until a staff member
     * tags a service on them — a real blind spot found in production
     * 2026-08-20 (2 of 3 new Meta leads that day sat here, invisible to
     * every other funnel figure, which all key off service_id = GMB).
     * Keyed on meta_leadgen_id rather than source=meta_ads — this class's
     * own established preference (see the class docblock) since source can
     * be unreliably overwritten by Meta's own auto-WhatsApp-message webhook
     * race.
     */
    public function awaitingServiceTag(?Carbon $from = null, ?Carbon $to = null): int
    {
        return Lead::query()
            ->whereNotNull('meta_leadgen_id')
            ->whereNull('service_id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();
    }

    /**
     * Whether a Lead belongs to the Visibility Audit cohort at all — either
     * it matches the eligibility rule (meta_leadgen_id + GMB) directly, or
     * it already has at least one funnel event (it clicked through a
     * tracked link at some point, even if its service tag later changed).
     * Used by CallLogController::store() to decide whether a logged call
     * should also become a manual_outreach touch.
     */
    public function isVisibilityAuditCohort(Lead $lead): bool
    {
        if ($lead->meta_leadgen_id !== null && $lead->service_id === $this->gmbServiceId()) {
            return true;
        }

        return $lead->visibilityAuditFunnelEvents()->exists();
    }

    private function eligibleLeadsQuery(?Carbon $from, ?Carbon $to)
    {
        return Lead::query()
            ->whereNotNull('meta_leadgen_id')
            ->where('service_id', $this->gmbServiceId())
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
    }

    private function gmbServiceId(): ?int
    {
        return Service::where('name', 'GMB')->value('id');
    }

    private function pct(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
    }
}
