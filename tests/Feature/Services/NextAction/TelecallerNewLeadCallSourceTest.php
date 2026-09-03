<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextAction\TelecallerNewLeadCallSource;

function telecallerNewLeadCallSource(): TelecallerNewLeadCallSource
{
    return app(TelecallerNewLeadCallSource::class);
}

it('returns null for a non-Telecaller user even with a qualifying lead', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['telecaller_id' => $support->id, 'status' => LeadStatus::New]);

    expect(telecallerNewLeadCallSource()->next($support))->toBeNull();
});

it('prompts a telecaller (as an additional role) to call their oldest new, uncalled lead', function () {
    // Real telecallers hold this as an additional role, never primary
    // (see [[lead-visibility-telecaller-assignment]]) — confirm that case
    // specifically, not just a primary-role fixture.
    $telecaller = User::factory()->role(UserRole::Accounts)->create();
    $telecaller->additionalRoles()->create(['role' => UserRole::Telecaller]);
    Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New, 'created_at' => now()->subHour()]);
    $older = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New, 'created_at' => now()->subDay()]);

    $action = telecallerNewLeadCallSource()->next($telecaller);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($older->id);
    expect($action->sourceKey)->toBe('telecaller_new_lead_call');
    expect($action->actionUrl)->toBe(route('calls.create', ['lead_id' => $older->id]));
});

it('excludes a lead that already has a call logged against it', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New]);
    CallLog::factory()->create(['callable_type' => Lead::class, 'callable_id' => $lead->id]);

    expect(telecallerNewLeadCallSource()->next($telecaller))->toBeNull();
});

it("does not surface another telecaller's lead", function () {
    // Fixture order matters here: LeadObserver::autoAssignTelecaller()
    // claims any lead with a null telecaller_id the instant an eligible
    // Telecaller exists, so both telecallers must exist before the lead
    // is created for its explicit assignment to actually stick (see
    // [[feedback-gotchas]]).
    $telecallerA = User::factory()->role(UserRole::Telecaller)->create();
    $telecallerB = User::factory()->role(UserRole::Telecaller)->create();
    Lead::factory()->create(['telecaller_id' => $telecallerB->id, 'status' => LeadStatus::New]);

    expect(telecallerNewLeadCallSource()->next($telecallerA))->toBeNull();
});

it('excludes a snoozed lead but includes it again once the snooze expires', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New]);

    NextActionSnooze::create([
        'user_id' => $telecaller->id,
        'source_key' => 'telecaller_new_lead_call',
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(telecallerNewLeadCallSource()->next($telecaller))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(telecallerNewLeadCallSource()->next($telecaller)?->subjectId)->toBe($lead->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create(['telecaller_id' => $telecaller->id, 'status' => LeadStatus::New]);

    telecallerNewLeadCallSource()->complete($telecaller, $lead->id);
})->throws(RuntimeException::class);
