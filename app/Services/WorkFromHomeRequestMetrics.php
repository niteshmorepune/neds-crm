<?php

namespace App\Services;

use App\Enums\WorkFromHomeRequestStatus;
use App\Models\WorkFromHomeRequest;
use Illuminate\Support\Carbon;

/**
 * WFH Dashboard / Overview — the Admin/Manager quick-summary strip shown
 * atop both the pending-approvals queue and the full Team WFH Records page,
 * plus the "is this date covered by an approved WFH request" lookup the
 * Attendance page uses to show a Remote badge. Mirrors
 * LeaveRequestMetrics's shape.
 */
class WorkFromHomeRequestMetrics
{
    /**
     * @return array{pending: int, approved_this_month: int, rejected_this_month: int, currently_remote: int}
     */
    public function summary(): array
    {
        $monthStart = now()->startOfMonth();
        $today = Carbon::today();

        return [
            'pending' => WorkFromHomeRequest::pending()->count(),
            'approved_this_month' => WorkFromHomeRequest::where('status', WorkFromHomeRequestStatus::Approved)
                ->where('reviewed_at', '>=', $monthStart)
                ->count(),
            'rejected_this_month' => WorkFromHomeRequest::where('status', WorkFromHomeRequestStatus::Rejected)
                ->where('reviewed_at', '>=', $monthStart)
                ->count(),
            'currently_remote' => WorkFromHomeRequest::where('status', WorkFromHomeRequestStatus::Approved)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    /**
     * Every business-day date (Y-m-d) covered by an Approved WFH request for
     * one user within [$from, $to] — used by AttendanceController::index()
     * to show a "Remote" badge without touching Attendance itself (approval
     * never writes to Attendance; the person still self-check-in/out as
     * normal).
     *
     * @return array<string, true>
     */
    public function remoteDatesFor(int $userId, Carbon $from, Carbon $to): array
    {
        $requests = WorkFromHomeRequest::where('user_id', $userId)
            ->where('status', WorkFromHomeRequestStatus::Approved)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get();

        $dates = [];
        foreach ($requests as $request) {
            foreach ($request->businessDays() as $date) {
                $dates[$date] = true;
            }
        }

        return $dates;
    }
}
