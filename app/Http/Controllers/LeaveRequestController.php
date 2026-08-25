<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use App\Enums\UserRole;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestReviewed;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\LeaveRequestMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $requests = LeaveRequest::where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->get();

        return view('leave-requests.index', [
            'requests' => $requests,
            'isManager' => $isManager,
            'pendingCount' => $isManager ? LeaveRequest::pending()->count() : 0,
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
            'reason' => $request->string('reason'),
            'status' => LeaveRequestStatus::Pending,
        ]);

        $recipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Admin, UserRole::Manager)
            ->where('id', '!=', $request->user()->id)
            ->get();
        $recipients->each(fn (User $u) => $u->notify(new LeaveRequestSubmitted($leaveRequest)));

        return back()->with('status', 'Leave request submitted.');
    }

    /**
     * Cancels a still-pending request — relabeled to Cancelled rather than
     * hard-deleted, so it stays permanently visible in the employee's own
     * leave history (matching this app's "relabel a real record instead of
     * making it disappear" convention elsewhere). Route/method name kept
     * for URL stability even though it no longer deletes the row.
     */
    public function destroy(LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->update(['status' => LeaveRequestStatus::Cancelled]);

        return back()->with('status', 'Leave request cancelled.');
    }

    public function approvals(Request $request, LeaveRequestMetrics $metrics): View
    {
        $this->authorize('viewApprovalQueue', LeaveRequest::class);

        $requests = LeaveRequest::pending()
            ->with('user')
            ->orderBy('start_date')
            ->get();

        return view('leave-requests.approvals', [
            'requests' => $requests,
            'summary' => $metrics->summary(),
        ]);
    }

    /**
     * Team Leave Records — the full, filterable history (not just pending)
     * for Admin/Manager, per the requirements doc's "Admin / Manager — Team
     * Leave Records" ask. Distinct from approvals() above (an action queue
     * of pending-only requests) — this is a browse/filter view with no
     * approve/reject actions of its own.
     */
    public function team(Request $request, LeaveRequestMetrics $metrics): View
    {
        $this->authorize('viewApprovalQueue', LeaveRequest::class);

        $requests = LeaveRequest::query()
            ->with(['user', 'reviewer'])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->value()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('start_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('start_date', '<=', $request->date('to')))
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        return view('leave-requests.team', [
            'requests' => $requests,
            'summary' => $metrics->summary(),
            'employees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => LeaveRequestType::cases(),
            'statuses' => LeaveRequestStatus::cases(),
            'filters' => $request->only(['user_id', 'type', 'status', 'from', 'to']),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('review', $leaveRequest);
        abort_if($leaveRequest->status !== LeaveRequestStatus::Pending, 409);

        $leaveRequest->fill([
            'status' => LeaveRequestStatus::Approved,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        foreach ($leaveRequest->businessDays() as $date) {
            $attendance = Attendance::where('user_id', $leaveRequest->user_id)
                ->whereDate('date', $date)
                ->first() ?? new Attendance([
                    'user_id' => $leaveRequest->user_id,
                    'date' => $date,
                ]);

            $attendance->status = $leaveRequest->type === LeaveRequestType::HalfDay
                ? AttendanceStatus::HalfDay
                : AttendanceStatus::Leave;
            $attendance->save();
        }

        $leaveRequest->user?->notify(new LeaveRequestReviewed($leaveRequest));

        return back()->with('status', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('review', $leaveRequest);
        abort_if($leaveRequest->status !== LeaveRequestStatus::Pending, 409);

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $leaveRequest->fill([
            'status' => LeaveRequestStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ])->save();

        $leaveRequest->user?->notify(new LeaveRequestReviewed($leaveRequest));

        return back()->with('status', 'Leave request rejected.');
    }
}
