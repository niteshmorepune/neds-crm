<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestReviewed;
use App\Notifications\LeaveRequestSubmitted;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->employee = User::factory()->role(UserRole::Sales)->create();
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('lets an employee submit a leave request and notifies admin/manager', function () {
    Notification::fake();

    // A Monday-Tuesday range, chosen so business-day counting is unambiguous.
    $start = now()->addWeek()->startOfWeek(); // Monday
    $end = $start->copy()->addDay(); // Tuesday

    $this->actingAs($this->employee)->post(route('leave-requests.store'), [
        'type' => LeaveRequestType::FullDay->value,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
        'reason' => 'Family function',
    ])->assertRedirect();

    $leaveRequest = LeaveRequest::where('user_id', $this->employee->id)->firstOrFail();
    expect($leaveRequest->status)->toBe(LeaveRequestStatus::Pending)
        ->and($leaveRequest->type)->toBe(LeaveRequestType::FullDay);

    Notification::assertSentTo($this->manager, LeaveRequestSubmitted::class);
});

it('lets an employee submit a half day leave request, and rejects it for a multi-day range', function () {
    $start = now()->addWeek()->startOfWeek();

    $this->actingAs($this->employee)->post(route('leave-requests.store'), [
        'type' => LeaveRequestType::HalfDay->value,
        'start_date' => $start->toDateString(),
        'end_date' => $start->toDateString(),
        'reason' => 'Doctor appointment',
    ])->assertRedirect();

    $leaveRequest = LeaveRequest::where('user_id', $this->employee->id)->firstOrFail();
    expect($leaveRequest->type)->toBe(LeaveRequestType::HalfDay)
        ->and($leaveRequest->dayCount())->toBe(0.5);

    $this->actingAs($this->employee)->post(route('leave-requests.store'), [
        'type' => LeaveRequestType::HalfDay->value,
        'start_date' => $start->copy()->addDays(3)->toDateString(),
        'end_date' => $start->copy()->addDays(4)->toDateString(),
        'reason' => 'Doctor appointment',
    ])->assertSessionHasErrors('end_date');
});

it('rejects an overlapping pending request via validation', function () {
    $start = now()->addWeek()->startOfWeek();
    LeaveRequest::factory()->create([
        'user_id' => $this->employee->id,
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDays(2)->toDateString(),
    ]);

    $this->actingAs($this->employee)->post(route('leave-requests.store'), [
        'start_date' => $start->copy()->addDay()->toDateString(),
        'end_date' => $start->copy()->addDays(3)->toDateString(),
        'reason' => 'Overlapping',
    ])->assertSessionHasErrors('start_date');
});

it('forbids viewing or cancelling another user\'s request', function () {
    $other = User::factory()->role(UserRole::Sales)->create();
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $other->id]);

    $this->actingAs($this->employee)->delete(route('leave-requests.destroy', $leaveRequest))->assertForbidden();
});

it('lets an owner cancel their own pending request but not once decided', function () {
    $pending = LeaveRequest::factory()->create(['user_id' => $this->employee->id]);
    $this->actingAs($this->employee)->delete(route('leave-requests.destroy', $pending))->assertRedirect();
    expect(LeaveRequest::find($pending->id))->toBeNull();

    $decided = LeaveRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => LeaveRequestStatus::Approved,
    ]);
    $this->actingAs($this->employee)->delete(route('leave-requests.destroy', $decided))->assertForbidden();
});

it('forbids a non-manager from the approvals queue and review actions', function () {
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $this->employee->id]);

    $this->actingAs($this->employee)->get(route('leave-requests.approvals'))->assertForbidden();
    $this->actingAs($this->employee)->post(route('leave-requests.approve', $leaveRequest))->assertForbidden();
    $this->actingAs($this->employee)->post(route('leave-requests.reject', $leaveRequest))->assertForbidden();
});

it('approves a request, marks attendance as Leave for business days only, and notifies the requester', function () {
    Notification::fake();

    $monday = now()->addWeek()->startOfWeek();
    $leaveRequest = LeaveRequest::factory()->create([
        'user_id' => $this->employee->id,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->copy()->addDays(6)->toDateString(), // Mon-Sun
    ]);

    $this->actingAs($this->manager)->post(route('leave-requests.approve', $leaveRequest))->assertRedirect();

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequestStatus::Approved)
        ->and($leaveRequest->reviewed_by)->toBe($this->manager->id);

    // Monday through Saturday marked Leave; Sunday untouched.
    for ($i = 0; $i < 6; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', $date)->first();
        expect($attendance)->not->toBeNull()
            ->and($attendance->status)->toBe(AttendanceStatus::Leave);
    }
    $sunday = $monday->copy()->addDays(6)->toDateString();
    expect(Attendance::where('user_id', $this->employee->id)->whereDate('date', $sunday)->exists())->toBeFalse();

    Notification::assertSentTo($this->employee, LeaveRequestReviewed::class);
});

it('approves a half day request and marks attendance as Half Day, not Leave', function () {
    Notification::fake();

    $monday = now()->addWeek()->startOfWeek();
    $leaveRequest = LeaveRequest::factory()->create([
        'user_id' => $this->employee->id,
        'type' => LeaveRequestType::HalfDay,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->toDateString(),
    ]);

    $this->actingAs($this->manager)->post(route('leave-requests.approve', $leaveRequest))->assertRedirect();

    $attendance = Attendance::where('user_id', $this->employee->id)->whereDate('date', $monday->toDateString())->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance->status)->toBe(AttendanceStatus::HalfDay);
});

it('shows the reviewer name on a decided leave request', function () {
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $this->employee->id]);
    $this->actingAs($this->manager)->post(route('leave-requests.approve', $leaveRequest))->assertRedirect();

    $this->actingAs($this->employee)->get(route('leave-requests.index'))->assertOk()->assertSee($this->manager->name);
});

it('rejects a request with notes and leaves attendance untouched', function () {
    Notification::fake();

    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $this->employee->id]);

    $this->actingAs($this->manager)->post(route('leave-requests.reject', $leaveRequest), [
        'review_notes' => 'Team is short-staffed that week',
    ])->assertRedirect();

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequestStatus::Rejected)
        ->and($leaveRequest->review_notes)->toBe('Team is short-staffed that week');

    expect(Attendance::where('user_id', $this->employee->id)->exists())->toBeFalse();
    Notification::assertSentTo($this->employee, LeaveRequestReviewed::class);
});

it('blocks a manager from approving or rejecting their own request', function () {
    $ownRequest = LeaveRequest::factory()->create(['user_id' => $this->manager->id]);

    $this->actingAs($this->manager)->post(route('leave-requests.approve', $ownRequest))->assertForbidden();
    $this->actingAs($this->manager)->post(route('leave-requests.reject', $ownRequest))->assertForbidden();
});

it('cannot approve or reject an already-decided request', function () {
    $decided = LeaveRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => LeaveRequestStatus::Approved,
    ]);

    $this->actingAs($this->manager)->post(route('leave-requests.approve', $decided))->assertStatus(409);
    $this->actingAs($this->manager)->post(route('leave-requests.reject', $decided))->assertStatus(409);
});

it('renders the leave requests and approvals pages', function () {
    $this->actingAs($this->employee)->get(route('leave-requests.index'))->assertOk()->assertSee('Apply for Leave');
    $this->actingAs($this->manager)->get(route('leave-requests.approvals'))->assertOk()->assertSee('Leave Approvals');
});
