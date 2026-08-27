<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\VisibilityAuditTouchType;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin/Manager analytics dashboard over the Visibility Audit funnel —
 * stage-by-stage conversion with a daily trend, and whether AI-automated
 * WhatsApp sends or Sales/Telecaller manual follow-up (auto-logged from
 * Call Log entries — see CallLogController::logVisibilityAuditTouch())
 * actually moves a lead to purchase. Separate from
 * VisibilityAuditRecoveryController (the Sales/Telecaller worklist) — this
 * is the oversight/analytics view, same admin/manager-only gate as every
 * other Reports controller (mirrors ReportController::authorizePerformance();
 * no dedicated Policy class, same no-Policy convention as
 * EmployeeProfileController/ClientRadarController for admin/manager-only
 * pages with no per-record ownership concept).
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

    public function index(Request $request, VisibilityAuditFunnelMetrics $metrics): View
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);

        [$from, $to] = $this->dateRange($request);

        return view('reports.visibility-audit-funnel', [
            'funnel' => $metrics->funnelSummary($from, $to),
            'trend' => $metrics->trend($from, $to),
            'touchesByChannel' => $metrics->touchesByChannel($from, $to),
            'conversionByChannel' => $metrics->conversionByChannel($from, $to),
            'awaitingServiceTag' => $metrics->awaitingServiceTag($from, $to),
            'notYetInvited' => $metrics->notYetInvitedCount($from, $to),
            'failedTouches' => $metrics->failedTouchesCount($from, $to),
            'totalPurchases' => $metrics->totalPurchases($from, $to),
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
}
