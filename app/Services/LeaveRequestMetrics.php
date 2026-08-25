<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;

/**
 * Leave Dashboard / Overview — the Admin/Manager quick-summary strip shown
 * atop both the pending-approvals queue and the full Team Leave Records
 * page, so the two never show different numbers for the same thing.
 */
class LeaveRequestMetrics
{
    /**
     * @return array{pending: int, approved_this_month: int, rejected_this_month: int, currently_on_leave: int}
     */
    public function summary(): array
    {
        // "This month" cutoff matches DashboardMetrics' own convention
        // (plain now(), not an IST-shifted boundary) — reviewed_at is a
        // datetime, but this app tolerates UTC-calendar month boundaries
        // for "this month" aggregate counts everywhere else too.
        $monthStart = now()->startOfMonth();

        // start_date/end_date are date-only columns with no time component
        // (see LeaveRequest's casts) — comparing against a bare
        // Carbon::today() matches CollectionsMetrics' own precedent for
        // date-column "today" comparisons.
        $today = Carbon::today();

        return [
            'pending' => LeaveRequest::pending()->count(),
            'approved_this_month' => LeaveRequest::where('status', LeaveRequestStatus::Approved)
                ->where('reviewed_at', '>=', $monthStart)
                ->count(),
            'rejected_this_month' => LeaveRequest::where('status', LeaveRequestStatus::Rejected)
                ->where('reviewed_at', '>=', $monthStart)
                ->count(),
            'currently_on_leave' => LeaveRequest::where('status', LeaveRequestStatus::Approved)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }
}
