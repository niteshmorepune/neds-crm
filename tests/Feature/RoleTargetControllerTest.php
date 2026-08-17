<?php

use App\Enums\TargetMetric;
use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Models\RoleTarget;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('lets an admin and manager reach Team Targets, but forbids other roles', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($this->admin)->get(route('role-targets.index'))->assertOk();
    $this->actingAs($manager)->get(route('role-targets.index'))->assertOk();
    $this->actingAs($support)->get(route('role-targets.index'))->assertForbidden();
});

it('shows all 4 non-Sales roles with their KRA metric label', function () {
    $this->actingAs($this->admin)->get(route('role-targets.index'))
        ->assertOk()
        ->assertSee('Tickets resolved')
        ->assertSee('Collections recorded')
        ->assertSee('Tasks completed')
        ->assertSee('Calls made');
});

it('saves a per-rep target converting rupees to paise for the money metric', function () {
    $accountant = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($this->admin)->post(route('role-targets.store'), [
        'rep_targets' => [$accountant->id => '50000'],
    ])->assertRedirect();

    $target = RoleTarget::forPeriod($accountant->id, TargetMetric::CollectionsRecorded, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())->first();
    expect($target)->not->toBeNull()->and($target->target_value)->toBe(5000000); // paise
});

it('saves a per-rep target as a plain integer for a count metric', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    $this->actingAs($this->admin)->post(route('role-targets.store'), [
        'rep_targets' => [$telecaller->id => '150'],
    ])->assertRedirect();

    $target = RoleTarget::forPeriod($telecaller->id, TargetMetric::CallsMade, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())->first();
    expect($target->target_value)->toBe(150);
});

it('updates an existing target instead of duplicating it', function () {
    $intern = User::factory()->role(UserRole::Intern)->create();

    $this->actingAs($this->admin)->post(route('role-targets.store'), ['rep_targets' => [$intern->id => '20']]);
    $this->actingAs($this->admin)->post(route('role-targets.store'), ['rep_targets' => [$intern->id => '35']]);

    expect(RoleTarget::where('user_id', $intern->id)->count())->toBe(1)
        ->and(RoleTarget::where('user_id', $intern->id)->first()->target_value)->toBe(35);
});

it('saves a role-wide target', function () {
    $this->actingAs($this->admin)->post(route('role-targets.store'), [
        'role_wide_targets' => [UserRole::Support->value => '40'],
    ])->assertRedirect();

    $target = RoleTarget::forPeriod(null, TargetMetric::TicketsResolved, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())->first();
    expect($target)->not->toBeNull()->and($target->target_value)->toBe(40);
});

it('leaves an untouched target alone when the field is submitted blank', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    RoleTarget::factory()->forUser($telecaller->id, TargetMetric::CallsMade)->create(['target_value' => 80]);

    $this->actingAs($this->admin)->post(route('role-targets.store'), ['rep_targets' => [$telecaller->id => '']]);

    expect(RoleTarget::where('user_id', $telecaller->id)->first()->target_value)->toBe(80);
});

it('forbids a non admin/manager from saving targets even with the direct route', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->post(route('role-targets.store'), ['rep_targets' => [$support->id => '10']])
        ->assertForbidden();
});
