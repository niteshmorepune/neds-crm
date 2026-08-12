<?php

use App\Enums\UserRole;
use App\Models\TeamResource;
use App\Models\User;

it('lets Admin/Manager create, update, and delete any resource', function (UserRole $role) {
    $user = User::factory()->role($role)->create();
    $resource = TeamResource::factory()->create();

    expect($user->can('create', TeamResource::class))->toBeTrue()
        ->and($user->can('update', $resource))->toBeTrue()
        ->and($user->can('delete', $resource))->toBeTrue();
})->with([
    'admin' => UserRole::Admin,
    'manager' => UserRole::Manager,
]);

it('forbids a non-admin/manager from creating, updating, or deleting a resource', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $resource = TeamResource::factory()->create();

    expect($support->can('create', TeamResource::class))->toBeFalse()
        ->and($support->can('update', $resource))->toBeFalse()
        ->and($support->can('delete', $resource))->toBeFalse();
});

it('lets anyone view a resource with no role restriction', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $resource = TeamResource::factory()->create();

    expect($sales->can('view', $resource))->toBeTrue();
});

it('hides a role-restricted resource from a user without that role', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $resource = TeamResource::factory()->create();
    $resource->syncVisibleRoles([UserRole::Accounts]);

    expect($sales->can('view', $resource))->toBeFalse();
});

it('shows a role-restricted resource to a user who holds that role', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $resource = TeamResource::factory()->create();
    $resource->syncVisibleRoles([UserRole::Accounts]);

    expect($accounts->can('view', $resource))->toBeTrue();
});

it('always lets admin and manager view a role-restricted resource', function (UserRole $role) {
    $user = User::factory()->role($role)->create();
    $resource = TeamResource::factory()->create();
    $resource->syncVisibleRoles([UserRole::Support]);

    expect($user->can('view', $resource))->toBeTrue();
})->with([
    'admin' => UserRole::Admin,
    'manager' => UserRole::Manager,
]);

it('honors an additional (secondary) role for visibility, matching the sidebar role-union rule', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $sales->additionalRoles()->create(['role' => UserRole::Support]);

    $resource = TeamResource::factory()->create();
    $resource->syncVisibleRoles([UserRole::Support]);

    expect($sales->can('view', $resource))->toBeTrue();
});
