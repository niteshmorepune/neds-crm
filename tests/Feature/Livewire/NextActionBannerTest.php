<?php

use App\Enums\AttendanceStatus;
use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Livewire\NextActionBanner;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\NextActionSnooze;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// A real Monday, safely inside 9am-6pm office hours. Every test below
// freezes explicitly (daytime or evening) so behavior never depends on the
// real wall clock — DailyReportReminderSource/CheckOutReminderSource only
// activate after 18:00, which would otherwise make a plain
// Attendance::factory() fixture (checked in, not out) intermittently
// pre-empt whatever prompt a test is actually trying to assert on.
const BANNER_TEST_DAYTIME = '2026-09-07 11:00';

const BANNER_TEST_EVENING = '2026-09-07 18:30';

it('shows the attendance check-in prompt first, before any pending lead', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'attendance_check_in')
        ->assertSee('Mark your attendance')
        ->assertSee('Check in now');

    Carbon::setTestNow();
});

it('checking in via the button clears the attendance prompt and reveals the next one', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
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

    Carbon::setTestNow();
});

it('shows nothing once every daytime prompt is resolved', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertDontSee('Call ')
        ->assertSet('action', null);

    Carbon::setTestNow();
});

it('shows the attendance prompt to a non-Sales user, but never the Sales lead-call prompt', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['owner_id' => $support->id, 'status' => LeadStatus::New]);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'attendance_check_in');

    Attendance::factory()->for($support)->create();

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action', null);

    Carbon::setTestNow();
});

it('snoozes the current lead prompt, creating a NextActionSnooze row and clearing it from the next poll', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
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

    Carbon::setTestNow();
});

it('shows the meeting join link, opening in a new tab, ahead of a pending lead', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
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

    Carbon::setTestNow();
});

it('shows the lunch-hour wadesk AI reminder to an Admin during the window, linking out to wadesk.in', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-07 13:00', config('app.display_timezone')));
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
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $support = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id, 'subject' => 'Cannot log into portal']);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'support_new_ticket_reply')
        ->assertSee('Respond to: Cannot log into portal')
        ->assertSee(route('tickets.show', $ticket));

    Carbon::setTestNow();
});

it('shows the SLA-at-risk prompt, linking to the ticket page', function () {
    // .utc() here is deliberate, not redundant with the other frozen tests
    // in this file — this is the only one that reads a date-cast model
    // attribute back and calls a Carbon comparison (isSlaBreached()) on
    // it. Carbon::parse() with no explicit timezone silently defaults to
    // the *mocked* testNow's own timezone while frozen — freezing via a
    // bare Asia/Kolkata-parsed instant would leak that timezone into
    // Eloquent's own naive parse of the raw DB datetime string, shifting
    // sla_due_at by the IST offset and misreporting it as breached. See
    // [[feedback-gotchas]].
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone'))->utc());
    $support = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id, 'subject' => 'Payment gateway failing', 'sla_due_at' => now()->addHour()]);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'ticket_sla_at_risk')
        ->assertSee('SLA due soon: Payment gateway failing')
        ->assertSee(route('tickets.show', $ticket));

    Carbon::setTestNow();
});

it('shows the Telecaller lead-call prompt for someone holding it as an additional role', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $telecaller = User::factory()->role(UserRole::Accounts)->create();
    $telecaller->additionalRoles()->create(['role' => UserRole::Telecaller]);
    Attendance::factory()->for($telecaller)->create();
    $lead = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New, 'name' => 'Ramesh Kulkarni']);

    Livewire::actingAs($telecaller)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'telecaller_new_lead_call')
        ->assertSee('Call Ramesh Kulkarni');

    Carbon::setTestNow();
});

it('shows the daily-report reminder in the evening, linking to the Daily Report page', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_EVENING, config('app.display_timezone')));
    $support = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($support)->create(['check_in_at' => Carbon::parse('2026-09-07 09:30'), 'check_out_at' => null]);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'daily_report_reminder')
        ->assertSee('Submit your daily report')
        ->assertSee(route('daily-reports.index'));

    Carbon::setTestNow();
});

it('shows the Manager Action Center nudge, linking to the Action Center', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();
    Attendance::factory()->for($admin)->create();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    Livewire::actingAs($admin)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'manager_action_center_attention')
        ->assertSee('your attention')
        ->assertSee(route('manager-action-center.index'));

    Carbon::setTestNow();
});

it('checking out via the button clears the check-out reminder', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_EVENING, config('app.display_timezone')));
    $support = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($support)->create(['check_in_at' => Carbon::parse('2026-09-07 09:30'), 'check_out_at' => null]);
    DailyReport::factory()->create(['user_id' => $support->id, 'date' => Carbon::today(config('app.display_timezone'))]);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'checkout_reminder')
        ->assertSee('Check out for the day')
        ->call('complete')
        ->assertSet('action', null);

    $attendance = Attendance::where('user_id', $support->id)->whereDate('date', now())->first();
    expect($attendance->check_out_at)->not->toBeNull();

    Carbon::setTestNow();
});

it('creating the project via the button clears the deal-won-no-project prompt', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'won_at' => now(), 'title' => 'ADTA Group Website']);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action.source_key', 'deal_won_no_project')
        ->assertSee('Start the project for ADTA Group Website')
        ->assertSee('Create project now')
        ->call('complete')
        ->assertSet('action', null);

    expect(Project::where('deal_id', $deal->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('poll re-evaluates and picks up a newly-created lead', function () {
    Carbon::setTestNow(Carbon::parse(BANNER_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();

    $component = Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action', null);

    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $component->call('poll')->assertSet('action.subject_id', $lead->id);

    Carbon::setTestNow();
});
