<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Livewire\AttendanceWidget;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->user = User::factory()->role(UserRole::Sales)->create();
});

it('checks in then out via the dashboard widget', function () {
    Livewire::actingAs($this->user)->test(AttendanceWidget::class)->call('checkIn');

    $today = Attendance::where('user_id', $this->user->id)->firstOrFail();
    expect($today->check_in_at)->not->toBeNull()
        ->and($today->status)->toBe(AttendanceStatus::Present);

    Livewire::actingAs($this->user)->test(AttendanceWidget::class)->call('checkOut');
    expect($today->fresh()->check_out_at)->not->toBeNull();
});

it('does not duplicate a check-in', function () {
    Livewire::actingAs($this->user)->test(AttendanceWidget::class)->call('checkIn')->call('checkIn');

    expect(Attendance::where('user_id', $this->user->id)->count())->toBe(1);
});

it('lets a manager correct attendance and logs the change', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->post(route('attendance.corrections.store'), [
        'user_id' => $this->user->id,
        'date' => now()->toDateString(),
        'status' => 'leave',
        'notes' => 'Approved leave',
    ])->assertRedirect();

    $record = Attendance::where('user_id', $this->user->id)->firstOrFail();
    expect($record->status)->toBe(AttendanceStatus::Leave);

    // LogsActivity recorded the create.
    expect(Activity::where('subject_type', Attendance::class)->where('subject_id', $record->id)->exists())->toBeTrue();
});

it('forbids a non-manager from the corrections screen', function () {
    $this->actingAs($this->user)->get(route('attendance.corrections'))->assertForbidden();
});

it('exports a CSV of the month', function () {
    Attendance::factory()->create(['user_id' => $this->user->id, 'date' => now()->startOfMonth()]);

    $response = $this->actingAs($this->user)->get(route('attendance.export', ['month' => now()->format('Y-m')]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('renders the monthly attendance page', function () {
    $this->actingAs($this->user)->get(route('attendance.index'))->assertOk()->assertSee('My Attendance');
});
