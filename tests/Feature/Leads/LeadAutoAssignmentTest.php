<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

it('assigns a new unowned lead to the only active Sales user', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($sales->id);
});

it('picks the active Sales user with the fewest open leads', function () {
    $busy = User::factory()->role(UserRole::Sales)->create();
    $free = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->count(3)->ownedBy($busy->id)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($free->id);
});

it('does not count converted or lost leads as open workload', function () {
    $hasClosedLeads = User::factory()->role(UserRole::Sales)->create();
    $hasOpenLead = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->count(5)->ownedBy($hasClosedLeads->id)->create(['status' => LeadStatus::Converted]);
    Lead::factory()->ownedBy($hasOpenLead->id)->create(['status' => LeadStatus::New]);

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($hasClosedLeads->id);
});

it('ignores inactive Sales users when picking an assignee', function () {
    User::factory()->role(UserRole::Sales)->create(['is_active' => false]);
    $active = User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($active->id);
});

it('ignores non-Sales roles when picking an assignee', function () {
    User::factory()->role(UserRole::Manager)->create();
    User::factory()->role(UserRole::Admin)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($sales->id);
});

it('does not override an owner explicitly set at creation', function () {
    User::factory()->role(UserRole::Sales)->create(); // would otherwise win the assignment
    $chosen = User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->ownedBy($chosen->id)->create();

    expect($lead->fresh()->owner_id)->toBe($chosen->id);
});

it('leaves the lead unowned when no active Sales user exists', function () {
    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBeNull();
});

it('records a visible activity entry for the auto-assignment', function () {
    User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->create();

    expect($lead->activities()->where('event', 'updated')->count())->toBe(1);
});

it('auto-assigns independently of the AI_ENABLED flag', function () {
    config(['services.anthropic.enabled' => false]);
    $sales = User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($sales->id);
});
