<?php

namespace App\Services;

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferFunnelEventType;
use App\Enums\OfferKey;
use App\Enums\OfferPurchaseStatus;
use App\Models\Lead;
use App\Models\OfferPurchase;
use App\Models\VisibilityAuditPurchase;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The non-GBP counterpart to VisibilityAuditFunnelMetrics — powers both
 * App\Console\Commands\SendOfferFunnelRecoveryNudges (pendingRecommendationNudges()/
 * pendingOfferNudges()) and the team-wide Offer Funnel dashboard (everything
 * else below), for the 3 offers reached via the unified goal+budget
 * recommendation matrix (LeadGenerationAudit/WebsiteGrowthAudit/GrowthStrategy).
 * GbpAudit is deliberately excluded from the per-offer/nudge methods here —
 * that offer stays entirely on the existing, unchanged
 * VisibilityAuditFunnelMetrics pipeline, never double covered. See the
 * 2026-09-12 "unified funnel" and "team-wide offer funnel dashboard"
 * decisions log entries.
 *
 * Dashboard methods use a 5-stage shape (recommended -> notified -> viewed
 * -> reached_offer -> paid) deliberately matching
 * VisibilityAuditFunnelMetrics::funnelSummary()'s own 5 keys (eligible ->
 * invited -> landing_viewed -> checkout_viewed -> paid) so
 * OfferFunnelDashboardController can lay the two side by side in one
 * unified table — GBP has no separate "recommendation page vs. offer page"
 * split (one page does both), so a lead reaching this offer family's own
 * offer page and clicking through to checkout is folded into one
 * "reached_offer" column here rather than kept as two separate stages.
 */
class OfferFunnelMetrics
{
    /**
     * Leads whose recommendation was generated (and the first-touch message
     * sent, or attempted) but who never opened the recommendation page and
     * never reached their offer's own page either — the softer "gone quiet
     * before even looking" stage.
     *
     * @return Collection<int, Lead>
     */
    public function pendingRecommendationNudges(Carbon $olderThan): Collection
    {
        return $this->nonGbpQuery()
            ->whereNotNull('recommendation_generated_at')
            ->whereNull('recommendation_viewed_at')
            ->whereNull('offer_viewed_at')
            ->whereNull('offer_clicked_at')
            ->whereDoesntHave('offerPurchases', fn ($q) => $q->where('status', OfferPurchaseStatus::Paid))
            ->with(['offerFunnelEvents' => fn ($q) => $q->where('event_type', OfferFunnelEventType::RecommendationCreated)->latest()])
            ->get()
            ->filter(fn (Lead $lead) => $this->isPendingNudge($lead, $olderThan))
            ->values();
    }

    /**
     * Leads who reached their recommended offer's own page (viewed it, or
     * clicked its CTA to start checkout) but never completed a payment —
     * the hotter, closer-to-converting stage.
     *
     * @return Collection<int, Lead>
     */
    public function pendingOfferNudges(Carbon $olderThan): Collection
    {
        return $this->nonGbpQuery()
            ->where(fn ($q) => $q->whereNotNull('offer_viewed_at')->orWhereNotNull('offer_clicked_at'))
            ->whereDoesntHave('offerPurchases', fn ($q) => $q->where('status', OfferPurchaseStatus::Paid))
            ->with(['offerFunnelEvents' => fn ($q) => $q
                ->whereIn('event_type', [OfferFunnelEventType::OfferViewed, OfferFunnelEventType::OfferCtaClicked])
                ->latest()])
            ->get()
            ->filter(fn (Lead $lead) => $this->isPendingNudge($lead, $olderThan))
            ->values();
    }

    private function isPendingNudge(Lead $lead, Carbon $olderThan): bool
    {
        $latest = $lead->offerFunnelEvents->first();

        return $latest !== null && $latest->nudged_at === null && $latest->created_at->lte($olderThan)
            && ! $lead->hasStaffWhatsappReplySince($latest->created_at);
    }

    /**
     * Stage-by-stage counts for one non-GBP offer (or all 3 combined when
     * $offer is null), bounded by when the lead was recommended
     * (`recommendation_generated_at` — the moment this offer family's
     * matrix decision was made, the closest analog to
     * VisibilityAuditFunnelMetrics::eligibleLeadsQuery()'s own
     * Lead.created_at window). The `*_pct` keys are stage-to-stage
     * conversion (each vs. the stage immediately before it), null (not 0)
     * whenever the prior stage's count is 0 — same "—" instead of a
     * misleading 0%" convention as the VA dashboard.
     *
     * @return array{recommended: int, notified: int, viewed: int, reached_offer: int, paid: int, notified_pct: ?float, viewed_pct: ?float, reached_offer_pct: ?float, paid_pct: ?float, overall_pct: ?float}
     */
    public function funnelSummary(?OfferKey $offer, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $summary = [
            'recommended' => $this->offerLeadsQuery($offer, $from, $to)->count(),
            'notified' => $this->offerLeadsQuery($offer, $from, $to)->whereNotNull('recommendation_notified_at')->count(),
            'viewed' => $this->offerLeadsQuery($offer, $from, $to)->whereNotNull('recommendation_viewed_at')->count(),
            'reached_offer' => $this->offerLeadsQuery($offer, $from, $to)->whereNotNull('offer_clicked_at')->count(),
            'paid' => $this->offerLeadsQuery($offer, $from, $to)
                ->whereHas('offerPurchases', fn ($q) => $q->where('status', OfferPurchaseStatus::Paid))
                ->count(),
        ];

        $summary['notified_pct'] = $this->pct($summary['notified'], $summary['recommended']);
        $summary['viewed_pct'] = $this->pct($summary['viewed'], $summary['notified']);
        $summary['reached_offer_pct'] = $this->pct($summary['reached_offer'], $summary['viewed']);
        $summary['paid_pct'] = $this->pct($summary['paid'], $summary['reached_offer']);
        $summary['overall_pct'] = $this->pct($summary['paid'], $summary['recommended']);

        return $summary;
    }

    /**
     * funnelSummary() for each of the 3 non-GBP offers individually — the
     * dashboard's by-offer breakdown table. GbpAudit's own row is added by
     * the controller from VisibilityAuditFunnelMetrics::funnelSummary()
     * directly (remapped to this same 5-key shape), not from this class.
     *
     * @return array<string, array{recommended: int, notified: int, viewed: int, reached_offer: int, paid: int, notified_pct: ?float, viewed_pct: ?float, reached_offer_pct: ?float, paid_pct: ?float, overall_pct: ?float}>
     */
    public function byOfferBreakdown(?Carbon $from = null, ?Carbon $to = null): array
    {
        return collect([OfferKey::LeadGenerationAudit, OfferKey::WebsiteGrowthAudit, OfferKey::GrowthStrategy])
            ->mapWithKeys(fn (OfferKey $offer) => [$offer->value => $this->funnelSummary($offer, $from, $to)])
            ->all();
    }

    /**
     * Daily "recommended" vs. "paid" counts for the 3 non-GBP offers
     * combined, bucketed by Asia/Kolkata calendar date (never UTC — same
     * class of bug already caught once in this app, see CLAUDE.md's Create
     * Meeting entry). The controller merges this with
     * VisibilityAuditFunnelMetrics::trend()'s own eligible/paid series to
     * build the dashboard's single "all 4 offers" trend chart.
     *
     * @return list<array{label: string, recommended: int, paid: int}>
     */
    public function trend(Carbon $from, Carbon $to): array
    {
        $tz = config('app.display_timezone', 'Asia/Kolkata');
        $bucket = fn (Collection $rows, string $column) => $rows->groupBy(
            fn ($row) => $row->{$column}->timezone($tz)->toDateString()
        )->map->count();

        $recommendedByDay = $bucket(
            $this->offerLeadsQuery(null, $from, $to)->get(['recommendation_generated_at']),
            'recommendation_generated_at'
        );

        $paidByDay = $bucket(
            OfferPurchase::where('status', OfferPurchaseStatus::Paid)
                ->whereHas('lead', fn ($q) => $q->where('recommendation_offer_key', '!=', OfferKey::GbpAudit->value))
                ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
                ->get(['paid_at']),
            'paid_at'
        );

        $trend = [];
        foreach (CarbonPeriod::create($from->copy()->timezone($tz)->startOfDay(), '1 day', $to->copy()->timezone($tz)->startOfDay()) as $day) {
            $key = $day->toDateString();
            $trend[] = [
                'label' => $day->format('d M'),
                'recommended' => (int) ($recommendedByDay[$key] ?? 0),
                'paid' => (int) ($paidByDay[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * The actual Leads behind one funnelSummary()/byOfferBreakdown() stage
     * count, for the dashboard's drill-down. $stage is one of
     * funnelSummary()'s keys: recommended, notified, viewed, reached_offer,
     * paid. $offer null means "all 3 non-GBP offers combined", matching
     * funnelSummary(null, ...)'s own scope.
     *
     * @return Collection<int, Lead>
     */
    public function leadsForStage(?OfferKey $offer, string $stage, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $query = $this->offerLeadsQuery($offer, $from, $to)->with('owner');

        return match ($stage) {
            'recommended' => $query->latest('recommendation_generated_at')->get(),
            'notified' => $query->whereNotNull('recommendation_notified_at')->latest('recommendation_notified_at')->get(),
            'viewed' => $query->whereNotNull('recommendation_viewed_at')->latest('recommendation_viewed_at')->get(),
            'reached_offer' => $query->whereNotNull('offer_clicked_at')->latest('offer_clicked_at')->get(),
            'paid' => $query->whereHas('offerPurchases', fn ($q) => $q->where('status', OfferPurchaseStatus::Paid))
                ->latest('recommendation_generated_at')->get(),
            default => throw new \InvalidArgumentException("Unknown offer funnel stage [{$stage}]."),
        };
    }

    /**
     * Recommended + paid counts per goal x budget cell, across EVERY
     * offer (including GbpAudit) — deliberately offer-agnostic, since
     * `Lead.goal`/`budget_range`/`recommendation_generated_at` are shared
     * columns regardless of which offer the matrix ends up recommending.
     * "Paid" here means paid for ANY offer (checks both `offer_purchases`
     * and `visibility_audit_purchases` — see paidLeadIds()). A lead with no
     * goal/budget captured yet (real gap for some older/GMB-only-form Meta
     * leads — see CLAUDE.md's 2026-09-12 decisions log) is never
     * recommended in the first place, so it can't appear in any cell here.
     *
     * @return array<string, array<string, array{recommended: int, paid: int}>>
     */
    public function byGoalBudgetBreakdown(?Carbon $from = null, ?Carbon $to = null): array
    {
        $cells = [];
        foreach (LeadGoal::cases() as $goal) {
            foreach (LeadBudgetRange::cases() as $budget) {
                $cells[$goal->value][$budget->value] = ['recommended' => 0, 'paid' => 0];
            }
        }

        $leads = Lead::query()
            ->whereNotNull('goal')
            ->whereNotNull('budget_range')
            ->whereNotNull('recommendation_generated_at')
            ->when($from, fn ($q) => $q->where('recommendation_generated_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('recommendation_generated_at', '<=', $to))
            ->get(['id', 'goal', 'budget_range']);

        $paidLeadIds = $this->paidLeadIds($from, $to);

        foreach ($leads as $lead) {
            $cell = &$cells[$lead->goal->value][$lead->budget_range->value];
            $cell['recommended']++;
            if ($paidLeadIds->contains($lead->id)) {
                $cell['paid']++;
            }
            unset($cell);
        }

        return $cells;
    }

    /**
     * Union of paid lead ids across BOTH purchase tables — the one place
     * "paid" genuinely means "paid for any of the 4 offers", since
     * OfferPurchase (the 3 newer offers) and VisibilityAuditPurchase (GBP)
     * are separate tables with no shared parent. VisibilityAuditPurchase
     * has no status column — every row IS a completed payment (see that
     * model's own docblock), so created_at is the paid-date filter there;
     * OfferPurchase uses its own paid_at.
     */
    private function paidLeadIds(?Carbon $from, ?Carbon $to): \Illuminate\Support\Collection
    {
        $offerPaid = OfferPurchase::where('status', OfferPurchaseStatus::Paid)
            ->whereNotNull('lead_id')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to))
            ->pluck('lead_id');

        $vaPaid = VisibilityAuditPurchase::whereNotNull('lead_id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->pluck('lead_id');

        return $offerPaid->merge($vaPaid)->unique();
    }

    private function offerLeadsQuery(?OfferKey $offer, ?Carbon $from, ?Carbon $to)
    {
        return Lead::query()
            ->when(
                $offer !== null,
                fn ($q) => $q->where('recommendation_offer_key', $offer->value),
                fn ($q) => $q->where('recommendation_offer_key', '!=', OfferKey::GbpAudit->value)
            )
            ->whereNotNull('recommendation_generated_at')
            ->when($from, fn ($q) => $q->where('recommendation_generated_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('recommendation_generated_at', '<=', $to));
    }

    private function pct(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
    }

    private function nonGbpQuery()
    {
        return Lead::query()->where('recommendation_offer_key', '!=', OfferKey::GbpAudit->value);
    }
}
