<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestReviewed;
use App\Notifications\LeaveRequestSubmitted;
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

    public function destroy(LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return back()->with('status', 'Leave request cancelled.');
    }

    public function approvals(Request $request): View
    {
        $this->authorize('viewApprovalQueue', LeaveRequest::class);

        $requests = LeaveRequest::pending()
            ->with('user')
            ->orderBy('start_date')
            ->get();

        return view('leave-requests.approvals', ['requests' => $requests]);
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

            $attendance->status = AttendanceStatus::Leave;
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
