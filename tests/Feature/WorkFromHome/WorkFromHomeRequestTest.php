<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Enums\WorkFromHomeRequestStatus;
use App\Enums\WorkFromHomeRequestType;
use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkFromHomeRequest;
use App\Notifications\WorkFromHomeRequestReviewed;
use App\Notifications\WorkFromHomeRequestSubmitted;
use App\Services\WorkFromHomeRequestMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->employee = User::factory()->role(UserRole::Sales)->create();
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('lets an employee submit a WFH request and notifies admin/manager', function () {
    Notification::fake();

    $start = now()->addWeek()->startOfWeek(); // Monday
    $end = $start->copy()->addDay(); // Tuesday

    $this->actingAs($this->employee)->post(route('work-from-home.store'), [
        'type' => WorkFromHomeRequestType::FullDay->value,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
        'reason' => 'Family visiting',
    ])->assertRedirect();

    $wfhRequest = WorkFromHomeRequest::where('user_id', $this->employee->id)->firstOrFail();
    expect($wfhRequest->status)->toBe(WorkFromHomeRequestStatus::Pending)
        ->and($wfhRequest->type)->toBe(WorkFromHomeRequestType::FullDay);

    Notification::assertSentTo($this->manager, WorkFromHomeRequestSubmitted::class);
});

it('lets an employee submit a half day WFH request, and rejects it for a multi-day range', function () {
    $start = now()->addWeek()->startOfWeek();

    $this->actingAs($this->employee)->post(route('work-from-home.store'), [
        'type' => WorkFromHomeRequestType::HalfDay->value,
        'start_date' => $start->toDateString(),
        'end_date' => $start->toDateString(),
        'reason' => 'Doctor appointment',
    ])->assertRedirect();

    $wfhRequest = WorkFromHomeRequest::where('user_id', $this->employee->id)->firstOrFail();
    expect($wfhRequest->type)->toBe(WorkFromHomeRequestType::HalfDay)
        ->and($wfhRequest->dayCount())->toBe(0.5);

    $this->actingAs($this->employee)->post(route('work-from-home.store'), [
        'type' => WorkFromHomeRequestType::HalfDay->value,
        'start_date' => $start->copy()->addDays(3)->toDateString(),
        'end_date' => $start->copy()->addDays(4)->toDateString(),
        'reason' => 'Doctor appointment',
    ])->assertSessionHasErrors('end_date');
});

it('rejects an overlapping pending request via validation', function () {
    $start = now()->addWeek()->startOfWeek();
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id,
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDays(2)->toDateString(),
    ]);

    $this->actingAs($this->employee)->post(route('work-from-home.store'), [
        'start_date' => $start->copy()->addDay()->toDateString(),
        'end_date' => $start->copy()->addDays(3)->toDateString(),
        'reason' => 'Overlapping',
    ])->assertSessionHasErrors('start_date');
});

it('forbids viewing or cancelling another user\'s request', function () {
    $other = User::factory()->role(UserRole::Sales)->create();
    $wfhRequest = WorkFromHomeRequest::factory()->create(['user_id' => $other->id]);

    $this->actingAs($this->employee)->delete(route('work-from-home.destroy', $wfhRequest))->assertForbidden();
});

it('lets an owner cancel their own pending request but not once decided', function () {
    $pending = WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id]);
    $this->actingAs($this->employee)->delete(route('work-from-home.destroy', $pending))->assertRedirect();
    expect($pending->fresh()->status)->toBe(WorkFromHomeRequestStatus::Cancelled);

    $decided = WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => WorkFromHomeRequestStatus::Approved,
    ]);
    $this->actingAs($this->employee)->delete(route('work-from-home.destroy', $decided))->assertForbidden();
});

it('forbids a non-manager from the approvals queue and review actions', function () {
    $wfhRequest = WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id]);

    $this->actingAs($this->employee)->get(route('work-from-home.approvals'))->assertForbidden();
    $this->actingAs($this->employee)->post(route('work-from-home.approve', $wfhRequest))->assertForbidden();
    $this->actingAs($this->employee)->post(route('work-from-home.reject', $wfhRequest))->assertForbidden();
});

it('approves a request and notifies the requester WITHOUT touching Attendance', function () {
    Notification::fake();

    $monday = now()->addWeek()->startOfWeek();
    $wfhRequest = WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->copy()->addDays(6)->toDateString(), // Mon-Sun
    ]);

    $this->actingAs($this->manager)->post(route('work-from-home.approve', $wfhRequest))->assertRedirect();

    $wfhRequest->refresh();
    expect($wfhRequest->status)->toBe(WorkFromHomeRequestStatus::Approved)
        ->and($wfhRequest->reviewed_by)->toBe($this->manager->id);

    // Unlike LeaveRequest::approve(), approving a WFH request never writes
    // an Attendance row — the person still self-check-in/out as normal.
    expect(Attendance::where('user_id', $this->employee->id)->exists())->toBeFalse();

    Notification::assertSentTo($this->employee, WorkFromHomeRequestReviewed::class);
});

it('shows the reviewer name on a decided WFH request', function () {
    $wfhRequest = WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id]);
    $this->actingAs($this->manager)->post(route('work-from-home.approve', $wfhRequest))->assertRedirect();

    $this->actingAs($this->employee)->get(route('work-from-home.index'))->assertOk()->assertSee($this->manager->name);
});

it('rejects a request with notes and leaves attendance untouched', function () {
    Notification::fake();

    $wfhRequest = WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id]);

    $this->actingAs($this->manager)->post(route('work-from-home.reject', $wfhRequest), [
        'review_notes' => 'Need you in office that week',
    ])->assertRedirect();

    $wfhRequest->refresh();
    expect($wfhRequest->status)->toBe(WorkFromHomeRequestStatus::Rejected)
        ->and($wfhRequest->review_notes)->toBe('Need you in office that week');

    expect(Attendance::where('user_id', $this->employee->id)->exists())->toBeFalse();
    Notification::assertSentTo($this->employee, WorkFromHomeRequestReviewed::class);
});

it('blocks a manager from approving or rejecting their own request', function () {
    $ownRequest = WorkFromHomeRequest::factory()->create(['user_id' => $this->manager->id]);

    $this->actingAs($this->manager)->post(route('work-from-home.approve', $ownRequest))->assertForbidden();
    $this->actingAs($this->manager)->post(route('work-from-home.reject', $ownRequest))->assertForbidden();
});

it('cannot approve or reject an already-decided request', function () {
    $decided = WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => WorkFromHomeRequestStatus::Approved,
    ]);

    $this->actingAs($this->manager)->post(route('work-from-home.approve', $decided))->assertStatus(409);
    $this->actingAs($this->manager)->post(route('work-from-home.reject', $decided))->assertStatus(409);
});

it('renders the WFH requests and approvals pages', function () {
    $this->actingAs($this->employee)->get(route('work-from-home.index'))->assertOk()->assertSee('Request Work From Home');
    $this->actingAs($this->manager)->get(route('work-from-home.approvals'))->assertOk()->assertSee('WFH Approvals');
});

it('shows a cancelled request permanently in the employee\'s own history', function () {
    $pending = WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id, 'reason' => 'Internet install']);
    $this->actingAs($this->employee)->delete(route('work-from-home.destroy', $pending));

    $this->actingAs($this->employee)->get(route('work-from-home.index'))
        ->assertOk()
        ->assertSee('Internet install')
        ->assertSee('Cancelled');
});

it('shows the WFH summary strip on the approvals page', function () {
    WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Pending]);
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Approved,
        'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
    ]);

    $this->actingAs($this->manager)->get(route('work-from-home.approvals'))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Approved this month')
        ->assertSee('Currently remote');
});

it('forbids a non-manager from the team WFH records page', function () {
    $this->actingAs($this->employee)->get(route('work-from-home.team'))->assertForbidden();
});

it('lets a manager browse the full team WFH history, including decided and cancelled requests', function () {
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Approved,
        'reviewed_by' => $this->manager->id, 'reviewed_at' => now(), 'reason' => 'Approved WFH',
    ]);
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Cancelled, 'reason' => 'Cancelled WFH',
    ]);

    $this->actingAs($this->manager)->get(route('work-from-home.team'))
        ->assertOk()
        ->assertSee('Team WFH Records')
        ->assertSee('Approved WFH')
        ->assertSee('Cancelled WFH')
        ->assertSee($this->employee->name);
});

it('filters team WFH records by employee, type, status, and date range', function () {
    $other = User::factory()->role(UserRole::Support)->create(['name' => 'Other WFH Employee']);
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id, 'type' => WorkFromHomeRequestType::FullDay,
        'status' => WorkFromHomeRequestStatus::Approved, 'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        'start_date' => '2026-03-10', 'end_date' => '2026-03-11', 'reason' => 'March WFH',
    ]);
    WorkFromHomeRequest::factory()->create([
        'user_id' => $other->id, 'type' => WorkFromHomeRequestType::HalfDay,
        'status' => WorkFromHomeRequestStatus::Pending, 'start_date' => '2026-06-01', 'end_date' => '2026-06-01',
        'reason' => 'Other employee WFH',
    ]);

    $this->actingAs($this->manager)->get(route('work-from-home.team', ['user_id' => $this->employee->id]))
        ->assertOk()->assertSee('March WFH')->assertDontSee('Other employee WFH');

    $this->actingAs($this->manager)->get(route('work-from-home.team', ['status' => WorkFromHomeRequestStatus::Pending->value]))
        ->assertOk()->assertSee('Other employee WFH')->assertDontSee('March WFH');

    $this->actingAs($this->manager)->get(route('work-from-home.team', ['type' => WorkFromHomeRequestType::HalfDay->value]))
        ->assertOk()->assertSee('Other employee WFH')->assertDontSee('March WFH');

    $this->actingAs($this->manager)->get(route('work-from-home.team', ['from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()->assertSee('March WFH')->assertDontSee('Other employee WFH');
});

it('counts currently-remote, this-month approved/rejected, and pending correctly in the summary', function () {
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Approved,
        'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
    ]);
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Approved,
        'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
        'start_date' => now()->subMonths(2)->toDateString(), 'end_date' => now()->subMonths(2)->addDay()->toDateString(),
    ]);
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Rejected,
        'reviewed_by' => $this->manager->id, 'reviewed_at' => now(),
    ]);
    WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Pending]);
    WorkFromHomeRequest::factory()->create(['user_id' => $this->employee->id, 'status' => WorkFromHomeRequestStatus::Cancelled]);

    $summary = app(WorkFromHomeRequestMetrics::class)->summary();

    expect($summary['pending'])->toBe(1)
        ->and($summary['approved_this_month'])->toBe(2)
        ->and($summary['rejected_this_month'])->toBe(1)
        ->and($summary['currently_remote'])->toBe(1);
});

it('surfaces a Remote badge on Attendance for a date an approved WFH request covers, without changing attendance status', function () {
    $monday = now()->startOfWeek(); // this week's Monday — always <= today, never Sunday.
    WorkFromHomeRequest::factory()->create([
        'user_id' => $this->employee->id,
        'status' => WorkFromHomeRequestStatus::Approved,
        'reviewed_by' => $this->manager->id,
        'reviewed_at' => now(),
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->toDateString(),
    ]);
    Attendance::create([
        'user_id' => $this->employee->id,
        'date' => $monday->toDateString(),
        'status' => AttendanceStatus::Present,
        'check_in_at' => $monday->copy()->setTime(9, 30),
    ]);

    $response = $this->actingAs($this->employee)
        ->get(route('attendance.index', ['month' => $monday->format('Y-m')]))
        ->assertOk();

    $response->assertSee('Remote');
    expect(Attendance::where('user_id', $this->employee->id)->whereDate('date', $monday)->first()->status)
        ->toBe(AttendanceStatus::Present);
});
