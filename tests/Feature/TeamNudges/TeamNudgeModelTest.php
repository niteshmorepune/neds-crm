<?php

use App\Enums\UserRole;
use App\Models\TeamNudge;
use App\Models\User;

it('currentPeriodStart always lands on a Monday', function () {
    expect(TeamNudge::currentPeriodStart()->isMonday())->toBeTrue();
});

it('scopeActive excludes an inactive nudge', function () {
    $active = TeamNudge::factory()->create(['is_active' => true]);
    $inactive = TeamNudge::factory()->create(['is_active' => false]);

    $ids = TeamNudge::active()->pluck('id');

    expect($ids)->toContain($active->id)->not->toContain($inactive->id);
});

it('scopeForUser includes a null-target (everyone) nudge for any role', function () {
    $everyone = TeamNudge::factory()->create(['target_role' => null]);
    $sales = User::factory()->role(UserRole::Sales)->create();

    expect(TeamNudge::forUser($sales)->pluck('id'))->toContain($everyone->id);
});

it('scopeForUser matches a nudge targeted at the user\'s primary role', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $forSupport = TeamNudge::factory()->create(['target_role' => UserRole::Support->value]);
    $forSales = TeamNudge::factory()->create(['target_role' => UserRole::Sales->value]);

    $ids = TeamNudge::forUser($support)->pluck('id');

    expect($ids)->toContain($forSupport->id)->not->toContain($forSales->id);
});

it('scopeForUser also matches a nudge targeted at an ADDITIONAL role, not just the primary role', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $sales->additionalRoles()->create(['role' => UserRole::Support]);

    $forSupport = TeamNudge::factory()->create(['target_role' => UserRole::Support->value]);

    expect(TeamNudge::forUser($sales->fresh())->pluck('id'))->toContain($forSupport->id);
});
