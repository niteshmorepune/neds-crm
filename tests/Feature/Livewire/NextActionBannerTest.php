<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Livewire\NextActionBanner;
use App\Models\Attendance;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\NextActionSnooze;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('shows the attendance check-in prompt first, before any pending lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'attendance_check_in')
        ->assertSee('Mark your attendance')
        ->assertSee('Check in now');
});

it('checking in via the button clears the attendance prompt and reveals the next one', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New, 'name' => 'Priya Deshmukh']);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'attendance_check_in')
        ->call('complete')
        ->assertSet('action.source_key', 'sales_new_lead_call')
        ->assertSet('action.subject_id', $lead->id)
        ->assertSee('Call Priya Deshmukh')
        ->assertSee(route('calls.create', ['lead_id' => $lead->id]));

    $attendance = Attendance::where('user_id', $sales->id)->whereDate('date', now())->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->status)->toBe(AttendanceStatus::Present);
});

it('shows nothing once both attendance and lead prompts are resolved', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertDontSee('Call ')
        ->assertSet('action', null);
});

it('shows the attendance prompt to a non-Sales user, but never the Sales lead-call prompt', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['owner_id' => $support->id, 'status' => LeadStatus::New]);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'attendance_check_in');

    Attendance::factory()->for($support)->create();

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action', null);
});

it('snoozes the current lead prompt, creating a NextActionSnooze row and clearing it from the next poll', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action.subject_id', $lead->id)
        ->call('snooze')
        ->assertSet('action', null);

    expect(NextActionSnooze::where('user_id', $sales->id)
        ->where('subject_type', Lead::class)
        ->where('subject_id', $lead->id)
        ->where('snoozed_until', '>', now())
        ->exists())->toBeTrue();
});

it('shows the meeting join link, opening in a new tab, ahead of a pending lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);
    Meeting::factory()->create([
        'user_id' => $sales->id,
        'title' => 'NEDS <> ADTA Group',
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->addMinutes(5),
    ]);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'meeting_starting_soon')
        ->assertSee('Join: NEDS <> ADTA Group')
        ->assertSee('https://meet.google.com/abc-defg-hij')
        ->assertSee('target="_blank"', false);
});

it('shows the lunch-hour wadesk AI reminder to an Admin during the window, linking out to wadesk.in', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-07 13:00', config('app.display_timezone'))); // a real Monday
    $admin = User::factory()->role(UserRole::Admin)->create();
    Attendance::factory()->for($admin)->create();

    Livewire::actingAs($admin)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'lunch_hour_wadesk_ai')
        ->assertSee('Turn on lunch-hour AI replies')
        ->assertSee('https://wadesk.in/numbers')
        ->assertSee('target="_blank"', false);

    Carbon::setTestNow();
});

it('snoozing the lunch-hour AI reminder clears it for the rest of the window', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-07 13:00', config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();
    Attendance::factory()->for($admin)->create();

    Livewire::actingAs($admin)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'lunch_hour_wadesk_ai')
        ->call('snooze')
        ->assertSet('action', null);

    Carbon::setTestNow();
});

it('shows the Support ticket-reply prompt, linking to the ticket page', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id, 'subject' => 'Cannot log into portal']);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'support_new_ticket_reply')
        ->assertSee('Respond to: Cannot log into portal')
        ->assertSee(route('tickets.show', $ticket));
});

it('shows the Telecaller lead-call prompt for someone holding it as an additional role', function () {
    $telecaller = User::factory()->role(UserRole::Accounts)->create();
    $telecaller->additionalRoles()->create(['role' => UserRole::Telecaller]);
    Attendance::factory()->for($telecaller)->create();
    $lead = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New, 'name' => 'Ramesh Kulkarni']);

    Livewire::actingAs($telecaller)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'telecaller_new_lead_call')
        ->assertSee('Call Ramesh Kulkarni');
});

it('poll re-evaluates and picks up a newly-created lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();

    $component = Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action', null);

    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $component->call('poll')->assertSet('action.subject_id', $lead->id);
});
