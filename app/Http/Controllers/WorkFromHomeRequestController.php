<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\WorkFromHomeRequestStatus;
use App\Enums\WorkFromHomeRequestType;
use App\Http\Requests\StoreWorkFromHomeRequestRequest;
use App\Models\User;
use App\Models\WorkFromHomeRequest;
use App\Notifications\WorkFromHomeRequestReviewed;
use App\Notifications\WorkFromHomeRequestSubmitted;
use App\Services\WorkFromHomeRequestMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Work From Home requests — employee self-service + admin/manager approval
 * queue, mirroring LeaveRequestController's shape exactly. The one real
 * difference: approve() never touches Attendance (see WorkFromHomeRequest's
 * class doc) — a WFH day is still a working day, so the person keeps
 * self-check-in/out as normal; AttendanceController::index() separately
 * surfaces a "Remote" badge for any date an approved request covers.
 */
class WorkFromHomeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $requests = WorkFromHomeRequest::where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->get();

        return view('work-from-home.index', [
            'requests' => $requests,
            'isManager' => $isManager,
            'pendingCount' => $isManager ? WorkFromHomeRequest::pending()->count() : 0,
        ]);
    }

    public function store(StoreWorkFromHomeRequestRequest $request): RedirectResponse
    {
        $workFromHomeRequest = WorkFromHomeRequest::create([
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
            'reason' => $request->string('reason'),
            'status' => WorkFromHomeRequestStatus::Pending,
        ]);

        $recipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Admin, UserRole::Manager)
            ->where('id', '!=', $request->user()->id)
            ->get();
        $recipients->each(fn (User $u) => $u->notify(new WorkFromHomeRequestSubmitted($workFromHomeRequest)));

        return back()->with('status', 'WFH request submitted.');
    }

    /**
     * Cancels a still-pending request — relabeled to Cancelled rather than
     * hard-deleted, same convention as LeaveRequestController::destroy().
     */
    public function destroy(WorkFromHomeRequest $workFromHomeRequest): RedirectResponse
    {
        $this->authorize('delete', $workFromHomeRequest);

        $workFromHomeRequest->update(['status' => WorkFromHomeRequestStatus::Cancelled]);

        return back()->with('status', 'WFH request cancelled.');
    }

    public function approvals(Request $request, WorkFromHomeRequestMetrics $metrics): View
    {
        $this->authorize('viewApprovalQueue', WorkFromHomeRequest::class);

        $requests = WorkFromHomeRequest::pending()
            ->with('user')
            ->orderBy('start_date')
            ->get();

        return view('work-from-home.approvals', [
            'requests' => $requests,
            'summary' => $metrics->summary(),
        ]);
    }

    /**
     * Team WFH Records — the full, filterable history (not just pending),
     * mirroring LeaveRequestController::team().
     */
    public function team(Request $request, WorkFromHomeRequestMetrics $metrics): View
    {
        $this->authorize('viewApprovalQueue', WorkFromHomeRequest::class);

        $requests = WorkFromHomeRequest::query()
            ->with(['user', 'reviewer'])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->value()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('start_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('start_date', '<=', $request->date('to')))
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        return view('work-from-home.team', [
            'requests' => $requests,
            'summary' => $metrics->summary(),
            'employees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => WorkFromHomeRequestType::cases(),
            'statuses' => WorkFromHomeRequestStatus::cases(),
            'filters' => $request->only(['user_id', 'type', 'status', 'from', 'to']),
        ]);
    }

    public function approve(Request $request, WorkFromHomeRequest $workFromHomeRequest): RedirectResponse
    {
        $this->authorize('review', $workFromHomeRequest);
        abort_if($workFromHomeRequest->status !== WorkFromHomeRequestStatus::Pending, 409);

        $workFromHomeRequest->fill([
            'status' => WorkFromHomeRequestStatus::Approved,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $workFromHomeRequest->user?->notify(new WorkFromHomeRequestReviewed($workFromHomeRequest));

        return back()->with('status', 'WFH request approved.');
    }

    public function reject(Request $request, WorkFromHomeRequest $workFromHomeRequest): RedirectResponse
    {
        $this->authorize('review', $workFromHomeRequest);
        abort_if($workFromHomeRequest->status !== WorkFromHomeRequestStatus::Pending, 409);

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $workFromHomeRequest->fill([
            'status' => WorkFromHomeRequestStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ])->save();

        $workFromHomeRequest->user?->notify(new WorkFromHomeRequestReviewed($workFromHomeRequest));

        return back()->with('status', 'WFH request rejected.');
    }
}
