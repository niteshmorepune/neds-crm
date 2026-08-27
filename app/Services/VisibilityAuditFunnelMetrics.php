<?php

namespace App\Services;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
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
     * event) but never reached checkout and never paid. $ownerId narrows to
     * one owner's own leads — used by the Recovery worklist's "Your Gaps"
     * section so a Sales user sees only their own queue, distinct from the
     * page's existing (unscoped, whole-team) tables above it.
     */
    public function stuckAtLanding(?int $ownerId = null): Collection
    {
        return $this->stuckQuery(VisibilityAuditFunnelEventType::LandingViewed, $ownerId)
            ->whereDoesntHave('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed))
            ->get();
    }

    /**
     * Leads who reached checkout (an attributed `payment_viewed` event) but
     * never completed a payment. $ownerId — see stuckAtLanding() above.
     */
    public function stuckAtCheckout(?int $ownerId = null): Collection
    {
        return $this->stuckQuery(VisibilityAuditFunnelEventType::PaymentViewed, $ownerId)->get();
    }

    /**
     * Narrows a stuckAtLanding()/stuckAtCheckout() collection to leads that
     * genuinely still need a human follow-up — excludes anyone staff has
     * already replied to (over WhatsApp) since their latest funnel event.
     * Shared by the AI Activity Summary panel (Admin/Manager) and the
     * Recovery worklist's "Your Gaps" section (whoever's viewing) so both
     * apply the identical exclusion rule instead of drifting apart.
     *
     * @param  Collection<int, Lead>  $stuckLeads
     * @return Collection<int, Lead>
     */
    public function needsFollowUp(Collection $stuckLeads): Collection
    {
        return $stuckLeads->reject(function (Lead $lead) {
            $latestEvent = $lead->visibilityAuditFunnelEvents->first();

            return $latestEvent === null || $lead->hasStaffWhatsappReplySince($latestEvent->created_at);
        })->values();
    }

    /**
     * stuckAtLanding(), narrowed to Leads whose latest landing_viewed event
     * hasn't been nudged yet and is older than $olderThan — used by
     * SendVisibilityAuditRecoveryNudges so a lead is only ever nudged once
     * per visit, not once per scheduler run. Also excludes a lead staff has
     * already replied to (over WhatsApp) since that visit — real incident,
     * 2026-08-21: a customer already mid-conversation with a human-sent
     * custom proposal still got the automated "you haven't claimed the
     * offer" nudge, since nothing previously checked for this.
     */
    public function pendingLandingNudges(Carbon $olderThan): Collection
    {
        return $this->stuckAtLanding()->filter(function (Lead $lead) use ($olderThan) {
            $latest = $lead->visibilityAuditFunnelEvents->first();

            return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan)
                && ! $lead->hasStaffWhatsappReplySince($latest->created_at);
        })->values();
    }

    /**
     * stuckAtCheckout(), same narrowing as pendingLandingNudges() above.
     */
    public function pendingCheckoutNudges(Carbon $olderThan): Collection
    {
        return $this->stuckAtCheckout()->filter(function (Lead $lead) use ($olderThan) {
            $latest = $lead->visibilityAuditFunnelEvents->first();

            return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan)
                && ! $lead->hasStaffWhatsappReplySince($latest->created_at);
        })->values();
    }

    /**
     * Eligible leads (meta_leadgen_id + GMB, see notYetInvitedCount()) whose
     * first invite still hasn't gone out, older than $olderThan — used by
     * SendVisibilityAuditFirstInviteSweep so a lead whose eligibility-time
     * dispatch silently no-op'd (e.g. wadesk config briefly unset, a
     * transient HTTP failure — SendVisibilityAuditFirstInviteJob never
     * throws, so a queue worker never retries it on its own) still gets
     * invited eventually instead of depending on someone noticing the
     * "Not yet invited" dashboard callout. Real incident, 2026-08-21: 3
     * leads (#234/#235/#236) sat eligible-but-uninvited for ~3 days with
     * zero send attempts logged, only found by checking that callout by
     * hand. Excludes a lead who already purchased — SendVisibilityAuditFirstInviteJob
     * re-checks this again immediately before sending regardless, same
     * dual-layer pattern as the recovery nudges' payment-race guard.
     */
    public function pendingFirstInvites(Carbon $olderThan): Collection
    {
        return $this->eligibleLeadsQuery(null, null)
            ->whereNull('visibility_audit_invited_at')
            ->whereDoesntHave('visibilityAuditPurchases')
            ->where('created_at', '<=', $olderThan)
            ->get();
    }

    /**
     * Purchases older than $olderThan still missing at least one channel of
     * the "audit in progress" nudge (step 2 of the post-payment conversion
     * pipeline) — used by SendVisibilityAuditInProgressNudges. Deliberately
     * "missing EITHER channel", not "missing both": the command dispatches
     * each job independently, gated on that job's own idempotency column,
     * so a purchase that already got its WhatsApp nudge but whose email
     * failed still needs to be surfaced here for the email side to retry.
     *
     * @return Collection<int, VisibilityAuditPurchase>
     */
    public function pendingInProgressNudges(Carbon $olderThan): Collection
    {
        return VisibilityAuditPurchase::query()
            ->where('created_at', '<=', $olderThan)
            ->where(fn ($q) => $q->whereNull('in_progress_notified_at')->orWhereNull('in_progress_notified_email_at'))
            ->get();
    }

    private function stuckQuery(VisibilityAuditFunnelEventType $type, ?int $ownerId = null)
    {
        return Lead::query()
            ->whereHas('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', $type))
            ->whereDoesntHave('visibilityAuditPurchases')
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
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
     * The actual Leads behind one funnelSummary() stage count, for the
     * dashboard's drill-down — e.g. "which 7 leads viewed the offer page".
     * $stage is one of the funnelSummary() keys: eligible, invited,
     * landing_viewed, checkout_viewed, paid. Each stage is evaluated
     * independently (not narrowed to leads who also hit every earlier
     * stage) so the returned count always matches the tile that was
     * clicked — same "each stage a subset in spirit, not a strict funnel
     * query" caveat documented on funnelSummary() itself.
     *
     * @return Collection<int, Lead>
     */
    public function leadsForStage(string $stage, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $withPaid = fn ($q) => $q->with(['owner', 'visibilityAuditPurchases' => fn ($p) => $p->latest()]);

        return match ($stage) {
            'eligible' => $withPaid($this->eligibleLeadsQuery($from, $to))->latest('created_at')->get(),
            'invited' => $withPaid($this->eligibleLeadsQuery($from, $to)->whereNotNull('visibility_audit_invited_at'))
                ->latest('visibility_audit_invited_at')->get(),
            'landing_viewed' => $withPaid($this->eligibleLeadsQuery($from, $to)
                ->whereHas('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::LandingViewed)))
                ->latest('created_at')->get(),
            'checkout_viewed' => $withPaid($this->eligibleLeadsQuery($from, $to)
                ->whereHas('visibilityAuditFunnelEvents', fn ($q) => $q->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed)))
                ->latest('created_at')->get(),
            'paid' => $withPaid($this->eligibleLeadsQuery($from, $to)
                ->whereHas('visibilityAuditPurchases'))
                ->latest('created_at')->get(),
            'not_invited' => $withPaid($this->eligibleLeadsQuery($from, $to)->whereNull('visibility_audit_invited_at'))
                ->latest('created_at')->get(),
            default => throw new \InvalidArgumentException("Unknown funnel stage [{$stage}]."),
        };
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
     * Eligible leads (meta_leadgen_id + GMB) the AI first-invite job hasn't
     * reached yet — the "who's supposed to get a WhatsApp message but
     * hasn't" gap, distinct from awaitingServiceTag() below (which is leads
     * that can't even become eligible yet). A lead sits here either because
     * the invite is still queued (transient, seconds-scale) or because every
     * send attempt so far failed (see failedTouchesCount()) — the message
     * log distinguishes the two.
     */
    public function notYetInvitedCount(?Carbon $from = null, ?Carbon $to = null): int
    {
        return $this->eligibleLeadsQuery($from, $to)->whereNull('visibility_audit_invited_at')->count();
    }

    /**
     * Every outbound AI-WhatsApp send *attempt* logged in the window — the
     * detailed "when and to whom" report behind touchesByChannel()'s bare
     * count, so the team can see exactly which message went to which lead
     * and spot anything that needs manual follow-up. $touchType/$success
     * narrow to one message type / outcome (used by the message-log page's
     * own filters); omit either for everything. Always AI-WhatsApp only —
     * a staff call's own detail already lives on the Lead's Call Log tab,
     * so this report stays scoped to the channel nobody else already lists.
     * $ownerId narrows to one owner's own leads — used by the Recovery
     * worklist's "Your Message Log" section so a Sales user sees only sends
     * to their own leads, distinct from the Admin/Manager dashboard's
     * unscoped message log.
     */
    public function touchLogQuery(?Carbon $from = null, ?Carbon $to = null, ?VisibilityAuditTouchType $touchType = null, ?bool $success = null, ?int $ownerId = null)
    {
        return VisibilityAuditTouch::query()
            ->where('channel', VisibilityAuditTouchChannel::AiWhatsapp)
            ->with(['lead' => fn ($q) => $q->withTrashed()->with('owner')])
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->when($touchType, fn ($q) => $q->where('touch_type', $touchType))
            ->when($success !== null, fn ($q) => $q->where('success', $success))
            ->when($ownerId, fn ($q) => $q->whereHas('lead', fn ($lq) => $lq->where('owner_id', $ownerId)))
            ->latest('occurred_at');
    }

    /**
     * How many AI-WhatsApp send attempts in the window failed outright — a
     * failure is captured (not just Log::warning'd) precisely so this figure
     * exists; a non-zero count here is the team's signal that a lead was
     * *supposed* to get a message and didn't, distinct from
     * notYetInvitedCount()'s "not attempted yet" gap.
     */
    public function failedTouchesCount(?Carbon $from = null, ?Carbon $to = null): int
    {
        return VisibilityAuditTouch::query()
            ->where('channel', VisibilityAuditTouchChannel::AiWhatsapp)
            ->where('success', false)
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->count();
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
     * The actual Leads behind awaitingServiceTag()'s count, oldest first —
     * so the AI Activity Summary panel can name the longest-waiting ones
     * instead of only showing a bare number. Same eligibility rule
     * (meta_leadgen_id set, service_id null), just returning rows instead
     * of a count.
     *
     * $ownerId narrows to one owner's own leads — see stuckAtLanding()'s
     * docblock for why (Recovery worklist "Your Gaps" section).
     *
     * @return Collection<int, Lead>
     */
    public function leadsAwaitingServiceTag(int $limit = 10, ?int $ownerId = null): Collection
    {
        return Lead::query()
            ->whereNotNull('meta_leadgen_id')
            ->whereNull('service_id')
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->oldest('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'created_at', 'owner_id']);
    }

    /**
     * Every VisibilityAuditTouch (any channel/type, success or failure) that
     * occurred outside office hours (9am-7pm Asia/Kolkata) since $since —
     * the "what did AI handle overnight" feed behind the AI Activity
     * Summary panel. Filtered in PHP (not SQL) since touch volume here is
     * small (well under 100 rows even across the whole funnel's history as
     * of this writing) and a hard 9/19 boundary in SQL would need a
     * timezone-aware HOUR() expression MySQL's query builder doesn't give a
     * clean cross-connection way to express.
     *
     * @return Collection<int, VisibilityAuditTouch>
     */
    public function afterHoursTouches(Carbon $since): Collection
    {
        $tz = config('app.display_timezone', 'Asia/Kolkata');

        return VisibilityAuditTouch::query()
            ->with(['lead' => fn ($q) => $q->withTrashed()])
            ->where('occurred_at', '>=', $since)
            ->orderBy('occurred_at')
            ->get()
            ->filter(function (VisibilityAuditTouch $touch) use ($tz) {
                $hour = (int) $touch->occurred_at->copy()->timezone($tz)->format('G');

                return $hour < 9 || $hour >= 19;
            })
            ->values();
    }

    /**
     * The latest customer_reply touch per Lead (see
     * WhatsappWebhookController::recordLeadMessage()) that's at least
     * $olderThan old and still has no staff WhatsApp reply since — "a real
     * customer sent something and nobody from staff has answered yet",
     * distinct from stuckAtLanding()/stuckAtCheckout() (which track funnel
     * *page* progress, not conversation replies). Reuses
     * Lead::hasStaffWhatsappReplySince() so the same "AI's own after-hours
     * holding reply doesn't count as answered" rule applies here too.
     *
     * $ownerId narrows to one owner's own leads — see stuckAtLanding()'s
     * docblock for why (Recovery worklist "Your Gaps" section).
     *
     * @return Collection<int, VisibilityAuditTouch>
     */
    public function unansweredInboundReplies(Carbon $olderThan, ?int $ownerId = null): Collection
    {
        return VisibilityAuditTouch::query()
            ->where('touch_type', VisibilityAuditTouchType::CustomerReply)
            ->where('occurred_at', '<=', $olderThan)
            ->when($ownerId, fn ($q) => $q->whereHas('lead', fn ($lq) => $lq->where('owner_id', $ownerId)))
            ->with('lead.owner')
            ->orderByDesc('occurred_at')
            ->get()
            ->unique('lead_id')
            ->filter(fn (VisibilityAuditTouch $touch) => $touch->lead !== null && ! $touch->lead->hasStaffWhatsappReplySince($touch->occurred_at))
            ->values();
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

    /**
     * This one Lead's current Visibility Audit funnel stage, for display on
     * the Lead's own page — a real gap found 2026-08-23: the Recovery
     * worklist/dashboard already show "reached checkout, didn't pay" for a
     * lead, but opening that exact lead's own record showed nothing of the
     * kind, since nothing surfaced this funnel-stage information there.
     * Returns null for a lead outside the VA cohort entirely (nothing to
     * show). Checked in the same priority order funnelSummary()'s stages
     * imply — gmeet_held outranks ready_awaiting_gmeet outranks paid
     * outranks stuck-at-checkout outranks stuck-at-landing, etc. — so a
     * lead only ever gets its furthest-reached stage, not every stage it
     * passed through. gmeet_held/ready_awaiting_gmeet/paid all carry a
     * purchase_id (the Lead page uses it to render the "Mark ready" action
     * against the right purchase) — every earlier stage has no purchase
     * yet, so purchase_id is simply absent from that stage's array.
     *
     * @return array{stage: string, label: string, tone: string, since: ?Carbon, purchase_id?: int}|null
     */
    public function funnelStatusFor(Lead $lead): ?array
    {
        if (! $this->isVisibilityAuditCohort($lead)) {
            return null;
        }

        $purchase = $lead->visibilityAuditPurchases()->latest()->first();
        if ($purchase !== null) {
            if ($purchase->audit_ready_at !== null) {
                if ($purchase->hasHeldGmeet()) {
                    return ['stage' => 'gmeet_held', 'label' => 'Gmeet held — ready to share the audit report', 'tone' => 'green', 'since' => $purchase->audit_ready_at, 'purchase_id' => $purchase->id];
                }

                return ['stage' => 'ready_awaiting_gmeet', 'label' => 'Audit ready — Gmeet not scheduled/held yet', 'tone' => 'amber', 'since' => $purchase->audit_ready_at, 'purchase_id' => $purchase->id];
            }

            return ['stage' => 'paid', 'label' => 'Paid — Visibility Audit purchased', 'tone' => 'green', 'since' => $purchase->created_at, 'purchase_id' => $purchase->id];
        }

        $checkoutEvent = $lead->visibilityAuditFunnelEvents()
            ->where('event_type', VisibilityAuditFunnelEventType::PaymentViewed)
            ->latest()->first();
        if ($checkoutEvent !== null) {
            return ['stage' => 'checkout_stuck', 'label' => "Reached checkout, hasn't paid", 'tone' => 'red', 'since' => $checkoutEvent->created_at];
        }

        $landingEvent = $lead->visibilityAuditFunnelEvents()
            ->where('event_type', VisibilityAuditFunnelEventType::LandingViewed)
            ->latest()->first();
        if ($landingEvent !== null) {
            return ['stage' => 'landing_stuck', 'label' => 'Viewed the offer page, no checkout yet', 'tone' => 'amber', 'since' => $landingEvent->created_at];
        }

        if ($lead->visibility_audit_invited_at !== null) {
            return ['stage' => 'invited', 'label' => "Invited via WhatsApp, hasn't viewed the offer page yet", 'tone' => 'gray', 'since' => $lead->visibility_audit_invited_at];
        }

        return ['stage' => 'eligible', 'label' => 'Eligible for the Visibility Audit invite, not yet sent', 'tone' => 'gray', 'since' => null];
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
