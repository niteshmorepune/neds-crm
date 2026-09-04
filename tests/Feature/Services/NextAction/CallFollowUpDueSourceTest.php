<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextAction\CallFollowUpDueSource;

function callFollowUpDueSource(): CallFollowUpDueSource
{
    return app(CallFollowUpDueSource::class);
}

it('applies with no role gate — a Sales rep gets prompted', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['name' => 'Priya Deshmukh', 'status' => LeadStatus::Contacted]);
    $callLog = CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
        'next_action' => 'Call back after she checks with her partner',
    ]);

    $action = callFollowUpDueSource()->next($sales);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($callLog->id);
    expect($action->title)->toBe('Follow-up call due: Priya Deshmukh');
    expect($action->body)->toBe('Call back after she checks with her partner');
    expect($action->actionUrl)->toBe(route('calls.create', ['lead_id' => $lead->id]));
});

it('applies with no role gate — a Telecaller gets prompted too', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    CallLog::factory()->create([
        'user_id' => $telecaller->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);

    expect(callFollowUpDueSource()->next($telecaller))->not->toBeNull();
});

it('falls back to a generic due message when no next_action was recorded', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
        'next_action' => null,
    ]);

    expect(callFollowUpDueSource()->next($sales)->body)->toContain('Due');
});

it('links to the customer form when the callable is a Customer, not a Lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['company_name' => 'Curamind']);
    CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Customer::class,
        'callable_id' => $customer->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);

    $action = callFollowUpDueSource()->next($sales);

    expect($action->title)->toBe('Follow-up call due: Curamind');
    expect($action->actionUrl)->toBe(route('calls.create', ['customer_id' => $customer->id]));
});

it('does not prompt a follow-up that is not due yet', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->addHour(),
    ]);

    expect(callFollowUpDueSource()->next($sales))->toBeNull();
});

it('does not prompt a call log with no follow-up set', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => null,
    ]);

    expect(callFollowUpDueSource()->next($sales))->toBeNull();
});

it("does not surface another user's follow-up", function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    CallLog::factory()->create([
        'user_id' => $other->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);

    expect(callFollowUpDueSource()->next($sales))->toBeNull();
});

it('skips a follow-up whose lead has since been soft-deleted', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);
    $lead->delete();

    expect(callFollowUpDueSource()->next($sales))->toBeNull();
});

it('picks the earliest-due follow-up when more than one qualifies', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();
    CallLog::factory()->create(['user_id' => $sales->id, 'callable_type' => Lead::class, 'callable_id' => $leadA->id, 'follow_up_at' => now()->subMinutes(5)]);
    $earlier = CallLog::factory()->create(['user_id' => $sales->id, 'callable_type' => Lead::class, 'callable_id' => $leadB->id, 'follow_up_at' => now()->subHours(2)]);

    expect(callFollowUpDueSource()->next($sales)?->subjectId)->toBe($earlier->id);
});

it('excludes a snoozed follow-up but includes it again once the snooze expires', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $callLog = CallLog::factory()->create([
        'user_id' => $sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);

    NextActionSnooze::create([
        'user_id' => $sales->id,
        'source_key' => 'call_follow_up_due',
        'subject_type' => CallLog::class,
        'subject_id' => $callLog->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(callFollowUpDueSource()->next($sales))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(callFollowUpDueSource()->next($sales)?->subjectId)->toBe($callLog->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $callLog = CallLog::factory()->create(['user_id' => $sales->id, 'callable_type' => Lead::class, 'callable_id' => $lead->id, 'follow_up_at' => now()->subMinutes(5)]);

    callFollowUpDueSource()->complete($sales, $callLog->id);
})->throws(RuntimeException::class);
