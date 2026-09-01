<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('routes a new lead to the campaign rule target instead of the least-loaded Sales user', function () {
    $target = User::factory()->role(UserRole::Sales)->create();
    $busy = User::factory()->role(UserRole::Sales)->create(); // would win round-robin otherwise
    LeadAssignmentRule::factory()->for($target, 'assignedUser')->create(['utm_campaign' => 'CRM-ERP-Pune-Aug2026-V1']);

    $lead = Lead::factory()->create(['utm_campaign' => 'CRM-ERP-Pune-Aug2026-V1']);

    expect($lead->fresh()->owner_id)->toBe($target->id);
});

it('routes a new lead to the service rule target when no campaign rule matches', function () {
    $service = Service::factory()->create();
    $target = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($target, 'assignedUser')->forService($service->id)->create();

    $lead = Lead::factory()->create(['service_id' => $service->id]);

    expect($lead->fresh()->owner_id)->toBe($target->id);
});

it('prefers a campaign rule over a service rule when both could match the same lead', function () {
    $service = Service::factory()->create();
    $campaignTarget = User::factory()->role(UserRole::Sales)->create();
    $serviceTarget = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($campaignTarget, 'assignedUser')->create(['utm_campaign' => 'CRM-ERP-Pune-Aug2026-V1']);
    LeadAssignmentRule::factory()->for($serviceTarget, 'assignedUser')->forService($service->id)->create();

    $lead = Lead::factory()->create(['utm_campaign' => 'CRM-ERP-Pune-Aug2026-V1', 'service_id' => $service->id]);

    expect($lead->fresh()->owner_id)->toBe($campaignTarget->id);
});

it('falls back to round-robin when a matching rule exists but is inactive', function () {
    $ruleTarget = User::factory()->role(UserRole::Sales)->create();
    $fallback = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($ruleTarget, 'assignedUser')->create(['utm_campaign' => 'X', 'active' => false]);

    // Make ruleTarget the busier of the two, so round-robin would pick $fallback —
    // proving the (inactive) rule really was ignored, not just coincidentally skipped.
    Lead::factory()->count(2)->ownedBy($ruleTarget->id)->create();

    $lead = Lead::factory()->create(['utm_campaign' => 'X']);

    expect($lead->fresh()->owner_id)->toBe($fallback->id);
});

it('falls back to round-robin when the rule target is no longer an active Sales user', function () {
    $deactivated = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);
    $fallback = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($deactivated, 'assignedUser')->create(['utm_campaign' => 'X']);

    $lead = Lead::factory()->create(['utm_campaign' => 'X']);

    expect($lead->fresh()->owner_id)->toBe($fallback->id);
});

it('falls back to round-robin when no rule matches at all', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for(User::factory()->role(UserRole::Sales)->create(), 'assignedUser')->create(['utm_campaign' => 'Unrelated-Campaign']);

    $lead = Lead::factory()->create(['utm_campaign' => 'X']);

    expect($lead->fresh()->owner_id)->toBe($sales->id);
});

it('does not override an owner explicitly set at creation, even if a rule would match', function () {
    $ruleTarget = User::factory()->role(UserRole::Sales)->create();
    $chosen = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($ruleTarget, 'assignedUser')->create(['utm_campaign' => 'X']);

    $lead = Lead::factory()->ownedBy($chosen->id)->create(['utm_campaign' => 'X']);

    expect($lead->fresh()->owner_id)->toBe($chosen->id);
});

it('lets admin/manager manage rules but forbids other roles', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $this->actingAs($admin)->get(route('lead-assignment-rules.index'))->assertOk();

    $sales = User::factory()->role(UserRole::Sales)->create();
    $this->actingAs($sales)->get(route('lead-assignment-rules.index'))->assertForbidden();
});

it('creates a campaign-matching rule via the admin form', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $target = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->post(route('lead-assignment-rules.store'), [
        'match_type' => 'campaign',
        'utm_campaign' => 'CRM-ERP-Pune-Aug2026-V1',
        'assigned_user_id' => $target->id,
    ])->assertRedirect();

    $rule = LeadAssignmentRule::first();
    expect($rule->utm_campaign)->toBe('CRM-ERP-Pune-Aug2026-V1')
        ->and($rule->service_id)->toBeNull()
        ->and($rule->assigned_user_id)->toBe($target->id);
});

it('creates a service-matching rule via the admin form', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $target = User::factory()->role(UserRole::Sales)->create();
    $service = Service::factory()->create();

    $this->actingAs($admin)->post(route('lead-assignment-rules.store'), [
        'match_type' => 'service',
        'service_id' => $service->id,
        'assigned_user_id' => $target->id,
    ])->assertRedirect();

    $rule = LeadAssignmentRule::first();
    expect($rule->service_id)->toBe($service->id)
        ->and($rule->utm_campaign)->toBeNull();
});

it('creates a VA-Paid rule via the admin form', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $target = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->post(route('lead-assignment-rules.store'), [
        'match_type' => 'va_paid',
        'assigned_user_id' => $target->id,
    ])->assertRedirect();

    $rule = LeadAssignmentRule::first();
    expect($rule->va_paid)->toBeTrue()
        ->and($rule->utm_campaign)->toBeNull()
        ->and($rule->service_id)->toBeNull()
        ->and($rule->assigned_user_id)->toBe($target->id);
});

it('rejects a second active VA-Paid rule', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $target = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($target, 'assignedUser')->vaPaid()->create();

    $this->actingAs($admin)->post(route('lead-assignment-rules.store'), [
        'match_type' => 'va_paid', 'assigned_user_id' => $target->id,
    ])->assertSessionHasErrors('match_type');
});

it('rejects a rule targeting a non-Sales or inactive user', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $inactiveSales = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);

    $this->actingAs($admin)->post(route('lead-assignment-rules.store'), [
        'match_type' => 'campaign', 'utm_campaign' => 'X', 'assigned_user_id' => $manager->id,
    ])->assertSessionHasErrors('assigned_user_id');

    $this->actingAs($admin)->post(route('lead-assignment-rules.store'), [
        'match_type' => 'campaign', 'utm_campaign' => 'Y', 'assigned_user_id' => $inactiveSales->id,
    ])->assertSessionHasErrors('assigned_user_id');
});

it('rejects a second active rule for the same campaign', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $target = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($target, 'assignedUser')->create(['utm_campaign' => 'X']);

    $this->actingAs($admin)->post(route('lead-assignment-rules.store'), [
        'match_type' => 'campaign', 'utm_campaign' => 'X', 'assigned_user_id' => $target->id,
    ])->assertSessionHasErrors('utm_campaign');
});

it('updates an existing active rule\'s assigned user in place without tripping its own uniqueness check', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $original = User::factory()->role(UserRole::Sales)->create();
    $replacement = User::factory()->role(UserRole::Sales)->create();
    $rule = LeadAssignmentRule::factory()->for($original, 'assignedUser')->create(['utm_campaign' => 'X']);

    $this->actingAs($admin)->put(route('lead-assignment-rules.update', $rule), [
        'match_type' => 'campaign',
        'utm_campaign' => 'X',
        'service_id' => '',
        'assigned_user_id' => $replacement->id,
        'active' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($rule->fresh()->assigned_user_id)->toBe($replacement->id);
});

it('deletes a rule', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $rule = LeadAssignmentRule::factory()->for(User::factory()->role(UserRole::Sales)->create(), 'assignedUser')->create();

    $this->actingAs($admin)->delete(route('lead-assignment-rules.destroy', $rule))->assertRedirect();

    expect(LeadAssignmentRule::find($rule->id))->toBeNull();
});
