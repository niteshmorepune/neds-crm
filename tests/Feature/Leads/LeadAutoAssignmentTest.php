<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLeadNotification;

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

/*
 * Telecaller round-robin — added 2026-09-03 alongside the Sales-visibility
 * fix, reversing the 2026-07-26 "shared calling queue, no ownership"
 * decision. Mirrors the Sales tests above exactly, but for telecaller_id,
 * a separate assignment checked independently of owner_id.
 */

it('assigns a new lead with no telecaller to the only active Telecaller user', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->telecaller_id)->toBe($telecaller->id);
});

it('picks the active Telecaller user with the fewest open assigned leads', function () {
    $busy = User::factory()->role(UserRole::Telecaller)->create();
    $free = User::factory()->role(UserRole::Telecaller)->create();
    Lead::factory()->count(3)->create(['telecaller_id' => $busy->id]);

    $lead = Lead::factory()->create();

    expect($lead->fresh()->telecaller_id)->toBe($free->id);
});

it('does not count converted or lost leads as open telecaller workload', function () {
    $hasClosedLeads = User::factory()->role(UserRole::Telecaller)->create();
    $hasOpenLead = User::factory()->role(UserRole::Telecaller)->create();
    Lead::factory()->count(5)->create(['telecaller_id' => $hasClosedLeads->id, 'status' => LeadStatus::Converted]);
    Lead::factory()->create(['telecaller_id' => $hasOpenLead->id, 'status' => LeadStatus::New]);

    $lead = Lead::factory()->create();

    expect($lead->fresh()->telecaller_id)->toBe($hasClosedLeads->id);
});

it('ignores inactive Telecaller users when picking an assignee', function () {
    User::factory()->role(UserRole::Telecaller)->create(['is_active' => false]);
    $active = User::factory()->role(UserRole::Telecaller)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->telecaller_id)->toBe($active->id);
});

it('never auto-assigns a Sales or Manager/Admin user as telecaller, even as an additional role', function () {
    User::factory()->role(UserRole::Sales)->withAdditionalRoles(UserRole::Telecaller)->create();
    User::factory()->role(UserRole::Manager)->create();
    User::factory()->role(UserRole::Admin)->create();

    $lead = Lead::factory()->create();

    // Telecaller auto-assignment, like Sales', is deliberately
    // primary-role-only (CLAUDE.md 2026-08-08 multi-role decision) — a
    // routing target must be unambiguous.
    expect($lead->fresh()->telecaller_id)->toBeNull();
});

it('does not override a telecaller_id explicitly set at creation', function () {
    User::factory()->role(UserRole::Telecaller)->create(); // would otherwise win the assignment
    $chosen = User::factory()->role(UserRole::Telecaller)->create();

    $lead = Lead::factory()->create(['telecaller_id' => $chosen->id]);

    expect($lead->fresh()->telecaller_id)->toBe($chosen->id);
});

it('leaves the lead without a telecaller when no active Telecaller user exists', function () {
    $lead = Lead::factory()->create();

    expect($lead->fresh()->telecaller_id)->toBeNull();
});

it('notifies the newly-assigned telecaller', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    $lead = Lead::factory()->create();

    expect($telecaller->fresh()->notifications()->where('type', NewLeadNotification::class)->count())->toBe(1);
});

it('assigns both an owner and a telecaller to the same new lead independently', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    $lead = Lead::factory()->create()->fresh();

    expect($lead->owner_id)->toBe($sales->id)
        ->and($lead->telecaller_id)->toBe($telecaller->id);
});
