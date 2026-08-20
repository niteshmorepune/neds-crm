<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
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
