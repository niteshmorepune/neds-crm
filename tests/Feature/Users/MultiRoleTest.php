<?php

use App\Enums\UserRole;
use App\Models\User;

it('has a role via the primary role column', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    expect($user->hasRole(UserRole::Sales))->toBeTrue();
    expect($user->hasRole(UserRole::Support))->toBeFalse();
});

it('has a role via an additional role, on top of the primary role', function () {
    $user = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();

    expect($user->hasRole(UserRole::Support))->toBeTrue();
    expect($user->hasRole(UserRole::Sales))->toBeTrue();
    expect($user->hasRole(UserRole::Admin))->toBeFalse();
});

it('isAdmin is true for a secondary Admin role', function () {
    $user = User::factory()->role(UserRole::Sales)->withAdditionalRoles(UserRole::Admin)->create();

    expect($user->isAdmin())->toBeTrue();
});

it('allRoles returns the primary role plus additional roles, deduped', function () {
    $user = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales, UserRole::Support)->create();

    expect($user->allRoles()->pluck('value')->all())->toBe(['support', 'sales']);
});

it('withAnyRole matches a user whose primary role matches', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    User::factory()->role(UserRole::Support)->create();

    $matched = User::withAnyRole(UserRole::Sales, UserRole::Admin)->get();

    expect($matched->pluck('id')->all())->toBe([$sales->id]);
});

it('withAnyRole matches a user whose additional role matches', function () {
    $secondary = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Admin)->create();
    User::factory()->role(UserRole::Support)->create();

    $matched = User::withAnyRole(UserRole::Admin)->get();

    expect($matched->pluck('id')->all())->toBe([$secondary->id]);
});

it('withAnyRole still excludes inactive users when chained after another where clause', function () {
    // Regression test: withAnyRole's OR must be grouped in its own closure —
    // without it, "is_active AND role OR EXISTS(...)" would leak inactive users.
    User::factory()->role(UserRole::Admin)->create(['is_active' => false]);
    $activeAdmin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);
    $activeSecondary = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Admin)->create(['is_active' => true]);
    User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Admin)->create(['is_active' => false]);

    $matched = User::where('is_active', true)->withAnyRole(UserRole::Admin, UserRole::Manager)->get();

    expect($matched->pluck('id')->sort()->values()->all())
        ->toBe(collect([$activeAdmin->id, $activeSecondary->id])->sort()->values()->all());
});
