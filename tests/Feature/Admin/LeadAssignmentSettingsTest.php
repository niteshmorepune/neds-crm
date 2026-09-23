<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\LeadAssignmentSetting;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a manager view the Force Lead Assignment page but forbids a sales user', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('lead-assignment-settings.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('lead-assignment-settings.index'))->assertForbidden();
});

it('lets an admin view the Force Lead Assignment page', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get(route('lead-assignment-settings.index'))->assertOk();
});

it('defaults the switch to disabled the first time it is read', function () {
    expect(LeadAssignmentSetting::current()->enabled)->toBeFalse();
});

it('lets a manager enable the switch for a chosen Sales rep, recording who and when', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $target = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($manager)->post(route('lead-assignment-settings.enable'), ['forced_user_id' => $target->id])->assertRedirect();

    $setting = LeadAssignmentSetting::current();
    expect($setting->enabled)->toBeTrue()
        ->and($setting->forced_user_id)->toBe($target->id)
        ->and($setting->updated_by)->toBe($manager->id);
});

it('rejects a forced_user_id that is not an active Sales user', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $notSales = User::factory()->role(UserRole::Support)->create();
    $inactiveSales = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);

    $this->actingAs($manager)->post(route('lead-assignment-settings.enable'), ['forced_user_id' => $notSales->id])->assertSessionHasErrors('forced_user_id');
    $this->actingAs($manager)->post(route('lead-assignment-settings.enable'), ['forced_user_id' => $inactiveSales->id])->assertSessionHasErrors('forced_user_id');

    expect(LeadAssignmentSetting::current()->enabled)->toBeFalse();
});

it('lets a manager disable the switch after enabling it', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $target = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentSetting::current()->update(['enabled' => true, 'forced_user_id' => $target->id, 'updated_by' => $manager->id]);

    $this->actingAs($manager)->post(route('lead-assignment-settings.disable'))->assertRedirect();

    expect(LeadAssignmentSetting::current()->enabled)->toBeFalse();
});

it('forbids a sales user from enabling or disabling', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $target = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->post(route('lead-assignment-settings.enable'), ['forced_user_id' => $target->id])->assertForbidden();
    $this->actingAs($sales)->post(route('lead-assignment-settings.disable'))->assertForbidden();

    expect(LeadAssignmentSetting::current()->enabled)->toBeFalse();
});

it('forces every new lead to the chosen rep, overriding an otherwise-matching assignment rule', function () {
    $forced = User::factory()->role(UserRole::Sales)->create();
    $ruleTarget = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentRule::factory()->for($ruleTarget, 'assignedUser')->create(['utm_campaign' => 'CRM-ERP-Pune-Aug2026-V1']);
    LeadAssignmentSetting::current()->update(['enabled' => true, 'forced_user_id' => $forced->id]);

    $lead = Lead::factory()->create(['utm_campaign' => 'CRM-ERP-Pune-Aug2026-V1']);

    expect($lead->fresh()->owner_id)->toBe($forced->id);
});

it('forces every new lead to the chosen rep even with no matching rule at all', function () {
    $forced = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create(); // would win round-robin otherwise
    LeadAssignmentSetting::current()->update(['enabled' => true, 'forced_user_id' => $forced->id]);

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($forced->id);
});

it('falls back to normal rule/round-robin behavior once the switch is disabled', function () {
    $forced = User::factory()->role(UserRole::Sales)->create();
    $fallback = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentSetting::current()->update(['enabled' => false, 'forced_user_id' => $forced->id]);

    // Make forced the busier of the two, so round-robin would pick fallback --
    // proving the (disabled) switch really was ignored, not just coincidentally skipped.
    Lead::factory()->count(2)->ownedBy($forced->id)->create();

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($fallback->id);
});

it('falls back to normal rule/round-robin behavior when the forced target is no longer an active Sales user', function () {
    $forced = User::factory()->role(UserRole::Sales)->create();
    $fallback = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentSetting::current()->update(['enabled' => true, 'forced_user_id' => $forced->id]);
    $forced->update(['is_active' => false]);

    $lead = Lead::factory()->create();

    expect($lead->fresh()->owner_id)->toBe($fallback->id);
});

it('does not reassign a lead that already has an owner even when the switch is on', function () {
    $forced = User::factory()->role(UserRole::Sales)->create();
    $existingOwner = User::factory()->role(UserRole::Sales)->create();
    LeadAssignmentSetting::current()->update(['enabled' => true, 'forced_user_id' => $forced->id]);

    $lead = Lead::factory()->create(['owner_id' => $existingOwner->id]);

    expect($lead->fresh()->owner_id)->toBe($existingOwner->id);
});
