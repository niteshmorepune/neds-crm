<?php

namespace App\Http\Controllers;

use App\Enums\OfferKey;
use App\Enums\UserRole;
use App\Enums\VisibilityAuditTouchType;
use App\Services\OfferFunnelMetrics;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin/Manager analytics dashboard over the WHOLE unified offer funnel —
 * all 4 offers (GbpAudit + the 3 reached via the goal+budget recommendation
 * matrix), since PR #182 unified how a Meta lead is routed to an offer. The
 * GBP-specific section (stage-by-stage conversion + AI/staff channel
 * breakdown, unchanged since before the unification) sits alongside a new
 * "all offers" section covering the other 3 (App\Services\OfferFunnelMetrics)
 * plus a combined by-offer table and goal x budget breakdown — see the
 * 2026-09-12 "team-wide offer funnel dashboard" decisions log entry for why
 * this was folded into the existing page rather than given its own sidebar
 * entry/route group. Also whether AI-automated WhatsApp sends or
 * Sales/Telecaller manual follow-up (auto-logged from Call Log entries —
 * see CallLogController::logVisibilityAuditTouch()) actually moves a GBP
 * lead to purchase. Separate from VisibilityAuditRecoveryController (the
 * Sales/Telecaller worklist) — this is the oversight/analytics view, same
 * admin/manager-only gate as every other Reports controller (mirrors
 * ReportController::authorizePerformance(); no dedicated Policy class, same
 * no-Policy convention as EmployeeProfileController/ClientRadarController
 * for admin/manager-only pages with no per-record ownership concept).
 */
class VisibilityAuditDashboardController extends Controller
{
    private const STAGES = [
        'eligible' => 'Eligible leads',
        'invited' => 'Invited via WhatsApp',
        'landing_viewed' => 'Viewed offer page',
        'checkout_viewed' => 'Reached checkout',
        'paid' => 'Paid',
        'not_invited' => 'Not yet invited',
    ];

    /**
     * The non-GBP offer funnel's own stage keys, in display order — used to
     * validate offerLeads()'s ?stage= and to drive the by-offer table's
     * column headers from one place.
     */
    private const OFFER_STAGES = [
        'recommended' => 'Recommended',
        'notified' => 'Notified (WhatsApp)',
        'viewed' => 'Viewed recommendation',
        'reached_offer' => 'Reached offer/checkout',
        'paid' => 'Paid',
    ];

    public function index(Request $request, VisibilityAuditFunnelMetrics $metrics, OfferFunnelMetrics $offerMetrics): View
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);

        [$from, $to] = $this->dateRange($request);

        $vaFunnel = $metrics->funnelSummary($from, $to);
        $vaTrend = $metrics->trend($from, $to);

        $byOffer = $offerMetrics->byOfferBreakdown($from, $to);
        $byOffer[OfferKey::GbpAudit->value] = $this->remapGbpSummary($vaFunnel);
        // Stable display order: GBP first (the original, longest-running
        // offer), then the 3 newer ones in the same order they're offered
        // on the recommendation page.
        $byOffer = collect([OfferKey::GbpAudit, OfferKey::LeadGenerationAudit, OfferKey::WebsiteGrowthAudit, OfferKey::GrowthStrategy])
            ->mapWithKeys(fn (OfferKey $offer) => [$offer->value => $byOffer[$offer->value]])
            ->all();

        $allOffersFunnel = $this->sumStages(array_values($byOffer));

        return view('reports.visibility-audit-funnel', [
            'funnel' => $vaFunnel,
            'trend' => $vaTrend,
            'touchesByChannel' => $metrics->touchesByChannel($from, $to),
            'conversionByChannel' => $metrics->conversionByChannel($from, $to),
            'awaitingServiceTag' => $metrics->awaitingServiceTag($from, $to),
            'notYetInvited' => $metrics->notYetInvitedCount($from, $to),
            'failedTouches' => $metrics->failedTouchesCount($from, $to),
            'totalPurchases' => $metrics->totalPurchases($from, $to),
            'allOffersFunnel' => $allOffersFunnel,
            'byOffer' => $byOffer,
            'offerStages' => self::OFFER_STAGES,
            'goalBudget' => $offerMetrics->byGoalBudgetBreakdown($from, $to),
            'combinedTrend' => $this->combineTrends($vaTrend, $offerMetrics->trend($from, $to)),
            'fromInput' => $request->string('from')->value() ?: $from->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
            'toInput' => $request->string('to')->value() ?: $to->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
        ]);
    }

    /**
     * Drill-down behind one by-offer table cell for a non-GBP offer — "which
     * N leads reached checkout for the Growth Strategy offer". GBP's own
     * cells link to the existing leads() action/stage names instead (see the
     * view) since that offer's data lives entirely in VisibilityAuditFunnelMetrics.
     */
    public function offerLeads(Request $request, OfferFunnelMetrics $offerMetrics): View
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $offer = OfferKey::tryFrom((string) $request->string('offer'));
        abort_if($offer === null || $offer === OfferKey::GbpAudit, 404);

        $stage = $request->string('stage')->value();
        abort_unless(array_key_exists($stage, self::OFFER_STAGES), 404);

        [$from, $to] = $this->dateRange($request);

        return view('reports.offer-funnel-leads', [
            'offer' => $offer,
            'stage' => $stage,
            'stageLabel' => self::OFFER_STAGES[$stage],
            'leads' => $offerMetrics->leadsForStage($offer, $stage, $from, $to),
            'fromInput' => $request->string('from')->value() ?: $from->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
            'toInput' => $request->string('to')->value() ?: $to->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
        ]);
    }

    /**
     * ALL Visibility Audit purchases in the window, regardless of Meta
     * attribution — the actual list behind totalPurchases()'s count on the
     * main dashboard. Deliberately unscoped by cohort, unlike leads() above.
     */
    public function purchases(Request $request, VisibilityAuditFunnelMetrics $metrics): View
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);

        [$from, $to] = $this->dateRange($request);

        $purchases = $metrics->purchasesQuery($from, $to)->paginate(50)->withQueryString();

        return view('reports.visibility-audit-funnel-purchases', [
            'purchases' => $purchases,
            'fromInput' => $request->string('from')->value() ?: $from->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
            'toInput' => $request->string('to')->value() ?: $to->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
        ]);
    }

    /**
     * Drill-down behind one funnel stage tile — "which N leads viewed the
     * offer page". Same date-range parsing/defaulting as index() so the
     * count on the list always matches the tile that was clicked.
     */
    public function leads(Request $request, VisibilityAuditFunnelMetrics $metrics): View
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $stage = $request->string('stage')->value();
        abort_unless(array_key_exists($stage, self::STAGES), 404);

        [$from, $to] = $this->dateRange($request);

        return view('reports.visibility-audit-funnel-leads', [
            'stage' => $stage,
            'stageLabel' => self::STAGES[$stage],
            'leads' => $metrics->leadsForStage($stage, $from, $to),
            'fromInput' => $request->string('from')->value() ?: $from->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
            'toInput' => $request->string('to')->value() ?: $to->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
        ]);
    }

    /**
     * The detailed "when and to whom" report behind the AI-WhatsApp channel
     * — every send attempt (success or failure) with lead/type/timestamp,
     * filterable by message type and outcome, so the team can spot anything
     * that needs manual follow-up (a failed send here, or a lead with no
     * attempt at all — index()'s "Not yet invited" callout).
     */
    public function messages(Request $request, VisibilityAuditFunnelMetrics $metrics): View
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);

        [$from, $to] = $this->dateRange($request);

        $touchType = $request->filled('type') ? VisibilityAuditTouchType::tryFrom($request->string('type')->value()) : null;
        $success = match ($request->string('outcome')->value()) {
            'success' => true,
            'failed' => false,
            default => null,
        };

        $touches = $metrics->touchLogQuery($from, $to, $touchType, $success)
            ->paginate(50)
            ->withQueryString();

        return view('reports.visibility-audit-funnel-messages', [
            'touches' => $touches,
            'touchTypes' => VisibilityAuditTouchType::cases(),
            'typeFilter' => $touchType,
            'outcomeFilter' => $request->string('outcome')->value(),
            'fromInput' => $request->string('from')->value() ?: $from->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
            'toInput' => $request->string('to')->value() ?: $to->copy()->timezone(config('app.display_timezone', 'Asia/Kolkata'))->toDateString(),
        ]);
    }

    /**
     * ?from=YYYY-MM-DD&to=YYYY-MM-DD, default the last 30 days. Parsed
     * against app.display_timezone (not Carbon::parse()'s default of
     * app.timezone/UTC) — same off-by-5:30 class of bug already caught once
     * in this app (Create Meeting, 2026-07-29; EmployeeProfileController's
     * Activity Timeline picker uses the identical pattern).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $tz = config('app.display_timezone', 'Asia/Kolkata');

        $from = $request->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $request->string('from')->value(), $tz)->startOfDay()->utc()
            : now($tz)->subDays(29)->startOfDay()->utc();

        $to = $request->filled('to')
            ? Carbon::createFromFormat('Y-m-d', $request->string('to')->value(), $tz)->endOfDay()->utc()
            : now($tz)->endOfDay()->utc();

        return [$from, $to];
    }

    /**
     * VisibilityAuditFunnelMetrics::funnelSummary()'s 5 keys (eligible ->
     * invited -> landing_viewed -> checkout_viewed -> paid), renamed to
     * OfferFunnelMetrics::funnelSummary()'s shape (recommended -> notified
     * -> viewed -> reached_offer -> paid) so GBP can sit as a 4th row in the
     * same by-offer table — see both services' own docblocks for why these
     * two shapes are equivalent stage-for-stage.
     *
     * @return array{recommended: int, notified: int, viewed: int, reached_offer: int, paid: int, notified_pct: ?float, viewed_pct: ?float, reached_offer_pct: ?float, paid_pct: ?float, overall_pct: ?float}
     */
    private function remapGbpSummary(array $vaFunnel): array
    {
        return [
            'recommended' => $vaFunnel['eligible'],
            'notified' => $vaFunnel['invited'],
            'viewed' => $vaFunnel['landing_viewed'],
            'reached_offer' => $vaFunnel['checkout_viewed'],
            'paid' => $vaFunnel['paid'],
            'notified_pct' => $vaFunnel['invited_pct'],
            'viewed_pct' => $vaFunnel['landing_pct'],
            'reached_offer_pct' => $vaFunnel['checkout_pct'],
            'paid_pct' => $vaFunnel['paid_pct'],
            'overall_pct' => $vaFunnel['overall_pct'],
        ];
    }

    /**
     * Sums the 4 by-offer rows (each already in the shared 5-stage shape)
     * into one "all offers combined" summary, recomputing the stage-to-stage
     * percentages from the summed counts rather than averaging the
     * per-offer percentages (which would be wrong whenever offers have very
     * different volumes).
     *
     * @param  list<array{recommended: int, notified: int, viewed: int, reached_offer: int, paid: int}>  $rows
     * @return array{recommended: int, notified: int, viewed: int, reached_offer: int, paid: int, notified_pct: ?float, viewed_pct: ?float, reached_offer_pct: ?float, paid_pct: ?float, overall_pct: ?float}
     */
    private function sumStages(array $rows): array
    {
        $sum = ['recommended' => 0, 'notified' => 0, 'viewed' => 0, 'reached_offer' => 0, 'paid' => 0];

        foreach ($rows as $row) {
            foreach ($sum as $key => $value) {
                $sum[$key] += $row[$key];
            }
        }

        $pct = fn (int $n, int $d) => $d > 0 ? round($n / $d * 100, 1) : null;

        $sum['notified_pct'] = $pct($sum['notified'], $sum['recommended']);
        $sum['viewed_pct'] = $pct($sum['viewed'], $sum['notified']);
        $sum['reached_offer_pct'] = $pct($sum['reached_offer'], $sum['viewed']);
        $sum['paid_pct'] = $pct($sum['paid'], $sum['reached_offer']);
        $sum['overall_pct'] = $pct($sum['paid'], $sum['recommended']);

        return $sum;
    }

    /**
     * Merges VisibilityAuditFunnelMetrics::trend()'s daily eligible/paid
     * series (GBP) with OfferFunnelMetrics::trend()'s daily recommended/paid
     * series (the other 3 offers) into one combined "all offers" trend —
     * both already iterate the identical CarbonPeriod for the same $from/$to,
     * so they're zipped by index rather than re-matched by date string.
     *
     * @param  list<array{label: string, eligible: int, paid: int}>  $vaTrend
     * @param  list<array{label: string, recommended: int, paid: int}>  $offerTrend
     * @return list<array{label: string, recommended: int, paid: int}>
     */
    private function combineTrends(array $vaTrend, array $offerTrend): array
    {
        $combined = [];

        foreach ($vaTrend as $i => $day) {
            $combined[] = [
                'label' => $day['label'],
                'recommended' => $day['eligible'] + ($offerTrend[$i]['recommended'] ?? 0),
                'paid' => $day['paid'] + ($offerTrend[$i]['paid'] ?? 0),
            ];
        }

        return $combined;
    }
}
