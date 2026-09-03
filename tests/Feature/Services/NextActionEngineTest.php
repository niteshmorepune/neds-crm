<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextActionEngine;

function nextActionEngine(): NextActionEngine
{
    return app(NextActionEngine::class);
}

it('returns null for a non-Sales user even with a qualifying lead', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['owner_id' => $support->id, 'status' => LeadStatus::New]);

    expect(nextActionEngine()->nextFor($support))->toBeNull();
});

it('prompts a Sales rep to call their oldest new, uncalled lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $newer = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New, 'created_at' => now()->subHour()]);
    $older = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New, 'created_at' => now()->subDay()]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($older->id);
    expect($action->actionUrl)->toBe(route('calls.create', ['lead_id' => $older->id]));
});

it('excludes a lead that already has a call logged against it', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);
    CallLog::factory()->create(['callable_type' => Lead::class, 'callable_id' => $lead->id]);

    expect(nextActionEngine()->nextFor($sales))->toBeNull();
});

it('excludes a lead that is not still New', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::Contacted]);

    expect(nextActionEngine()->nextFor($sales))->toBeNull();
});

it('excludes a snoozed lead but includes it again once the snooze expires', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    NextActionSnooze::create([
        'user_id' => $sales->id,
        'source_key' => 'sales_new_lead_call',
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(nextActionEngine()->nextFor($sales))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(nextActionEngine()->nextFor($sales)?->subjectId)->toBe($lead->id);
});

it('returns null once a Sales rep has no pending new leads left', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    expect(nextActionEngine()->nextFor($sales))->toBeNull();
});

it('does not surface another Sales rep\'s leads', function () {
    $repA = User::factory()->role(UserRole::Sales)->create();
    $repB = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $repB->id, 'status' => LeadStatus::New]);

    expect(nextActionEngine()->nextFor($repA))->toBeNull();
});
