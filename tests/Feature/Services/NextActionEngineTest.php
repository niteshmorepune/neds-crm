<?php

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Enums\TargetMetric;
use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\NextActionSnooze;
use App\Models\Quotation;
use App\Models\RoleTarget;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NextActionEngine;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function nextActionEngine(): NextActionEngine
{
    return app(NextActionEngine::class);
}

// A real Monday, safely inside 9am-6pm office hours — every test in this
// file freezes to a daytime or evening instant explicitly so behavior
// never depends on the real wall clock (DailyReportReminderSource and
// CheckOutReminderSource only activate after 18:00, which would otherwise
// make several of these tests flaky depending on when they happen to run).
const ENGINE_TEST_DAYTIME = '2026-09-07 11:00';

const ENGINE_TEST_EVENING = '2026-09-07 18:30';

it('shows the attendance prompt before a role-specific prompt, even when both are pending', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('attendance_check_in');
    expect($action->subjectId)->toBe($sales->id);
    // the lead is still there, just not surfaced yet
    expect(Lead::find($lead->id)->status)->toBe(LeadStatus::New);

    Carbon::setTestNow();
});

it('shows the meeting-starting-soon prompt before the Sales lead-call prompt, once checked in', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);
    $meeting = Meeting::factory()->create(['user_id' => $sales->id, 'meet_link' => 'https://meet.google.com/abc', 'occurred_at' => now()->addMinutes(5)]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('meeting_starting_soon');
    expect($action->subjectId)->toBe($meeting->id);

    Carbon::setTestNow();
});

it('shows a due call follow-up ahead of the sales lead-call prompt, once checked in', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);
    $followUpLead = Lead::factory()->create();
    $callLog = CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $followUpLead->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('call_follow_up_due');
    expect($action->subjectId)->toBe($callLog->id);

    Carbon::setTestNow();
});

it('shows the lunch-hour AI reminder to an Admin during the window, ahead of nothing else applicable', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-07 13:00', config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();
    Attendance::factory()->for($admin)->create();

    $action = nextActionEngine()->nextFor($admin);

    expect($action->sourceKey)->toBe('lunch_hour_wadesk_ai');

    Carbon::setTestNow();
});

it('shows the Manager Action Center nudge ahead of the team-target nudge, outside the lunch window', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();
    Attendance::factory()->for($admin)->create();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    $action = nextActionEngine()->nextFor($admin);

    expect($action->sourceKey)->toBe('manager_action_center_attention');

    Carbon::setTestNow();
});

it('falls through to the team-target nudge once the Action Center is clear', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-20 11:00', config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    Attendance::factory()->for($admin)->create();
    $intern = User::factory()->role(UserRole::Intern)->create();
    RoleTarget::factory()->forUser($intern->id, TargetMetric::TasksCompleted)->create([
        'period_start' => TargetPeriodType::Month->currentPeriodStart(),
        'target_value' => 100,
    ]);

    $action = nextActionEngine()->nextFor($admin);

    expect($action->sourceKey)->toBe('team_member_behind_target');
    expect($action->subjectId)->toBe($intern->id);

    Carbon::setTestNow();
});

it('shows the daily-report reminder ahead of the sales lead-call prompt once evening arrives', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_EVENING, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create(['check_in_at' => Carbon::parse('2026-09-07 09:30'), 'check_out_at' => null]);
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('daily_report_reminder');

    Carbon::setTestNow();
});

it('shows the check-out reminder ahead of the sales lead-call prompt, once the report is already submitted', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_EVENING, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create(['check_in_at' => Carbon::parse('2026-09-07 09:30'), 'check_out_at' => null]);
    DailyReport::factory()->create(['user_id' => $sales->id, 'date' => Carbon::today(config('app.display_timezone'))]);
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('checkout_reminder');

    Carbon::setTestNow();
});

it('falls through to the sales lead-call prompt once report and check-out are both resolved, even in the evening', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_EVENING, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create(['check_in_at' => Carbon::parse('2026-09-07 09:30'), 'check_out_at' => now()]);
    DailyReport::factory()->create(['user_id' => $sales->id, 'date' => Carbon::today(config('app.display_timezone'))]);
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('sales_new_lead_call');
    expect($action->subjectId)->toBe($lead->id);

    Carbon::setTestNow();
});

it('falls through to the next source once the earlier one has nothing pending', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('sales_new_lead_call');
    expect($action->subjectId)->toBe($lead->id);

    Carbon::setTestNow();
});

it('shows a fresh lead-call prompt ahead of the rest of the Sales journey, once checked in', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'won_at' => now()]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('sales_new_lead_call');
    expect($action->subjectId)->toBe($lead->id);

    Carbon::setTestNow();
});

it('walks the rest of the Sales journey in order once there is no fresh lead to call: won-deal, then quotation, then overdue invoice', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'won_at' => now()]);
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    $quotation = Quotation::factory()->create(['customer_id' => $customer->id, 'status' => QuotationStatus::Sent]);
    $quotation->forceFill(['updated_at' => now()->subDays(4)])->saveQuietly();
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    expect(nextActionEngine()->nextFor($sales)->sourceKey)->toBe('deal_won_no_project');

    // deal_won_no_project resolves via a real one-click complete(); the
    // other two only ever resolve by visiting their linked page, so the
    // test moves them along the same way the popup's own Snooze does.
    nextActionEngine()->completeFor($sales, 'deal_won_no_project', $deal->id);
    expect(nextActionEngine()->nextFor($sales)->sourceKey)->toBe('quotation_follow_up');

    NextActionSnooze::create([
        'user_id' => $sales->id,
        'source_key' => 'quotation_follow_up',
        'subject_type' => Quotation::class,
        'subject_id' => $quotation->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);
    expect(nextActionEngine()->nextFor($sales)->sourceKey)->toBe('overdue_invoice_follow_up');
    expect(nextActionEngine()->nextFor($sales)->subjectId)->toBe($invoice->id);

    Carbon::setTestNow();
});

it('shows the telecaller lead-call prompt for a user holding Telecaller as an additional role', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $telecaller = User::factory()->role(UserRole::Accounts)->create();
    $telecaller->additionalRoles()->create(['role' => UserRole::Telecaller]);
    Attendance::factory()->for($telecaller)->create();
    $lead = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($telecaller);

    expect($action->sourceKey)->toBe('telecaller_new_lead_call');
    expect($action->subjectId)->toBe($lead->id);

    Carbon::setTestNow();
});

it('shows the Support ticket-reply prompt once checked in', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $support = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id]);

    $action = nextActionEngine()->nextFor($support);

    expect($action->sourceKey)->toBe('support_new_ticket_reply');
    expect($action->subjectId)->toBe($ticket->id);

    Carbon::setTestNow();
});

it('shows the SLA-at-risk prompt ahead of the new-ticket-reply prompt, once checked in', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $support = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($support)->create();
    Ticket::factory()->create(['assignee_id' => $support->id]); // default SLA far out, would win support_new_ticket_reply on its own
    $atRisk = Ticket::factory()->create(['assignee_id' => $support->id, 'sla_due_at' => now()->addHour()]);

    $action = nextActionEngine()->nextFor($support);

    expect($action->sourceKey)->toBe('ticket_sla_at_risk');
    expect($action->subjectId)->toBe($atRisk->id);

    Carbon::setTestNow();
});

it('returns null once every source has nothing pending', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();

    expect(nextActionEngine()->nextFor($sales))->toBeNull();

    Carbon::setTestNow();
});

it('completeFor() dispatches to the matching source by key', function () {
    Carbon::setTestNow(Carbon::parse(ENGINE_TEST_DAYTIME, config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();

    nextActionEngine()->completeFor($user, 'attendance_check_in', $user->id);

    expect(nextActionEngine()->nextFor($user))->toBeNull();

    Carbon::setTestNow();
});

it('completeFor() aborts on an unknown source key', function () {
    $user = User::factory()->role(UserRole::Support)->create();

    nextActionEngine()->completeFor($user, 'not_a_real_source', $user->id);
})->throws(NotFoundHttpException::class);
