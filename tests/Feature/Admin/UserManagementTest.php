<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('lets an admin reach user management but forbids others', function () {
    $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->assertSee('Add user');
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('users.index'))->assertForbidden();
});

it('creates a staff user with a role and active status', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Priya Sales',
        'email' => 'priya@neds.test',
        'role' => UserRole::Sales->value,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_active' => '1',
    ])->assertRedirect(route('users.index'));

    $user = User::firstWhere('email', 'priya@neds.test');
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Sales)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});

it('can disable a user and the disabled user cannot log in', function () {
    $staff = User::factory()->role(UserRole::Support)->create(['password' => Hash::make('secret123')]);

    $this->actingAs($this->admin)->put(route('users.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'role' => UserRole::Support->value,
        'is_active' => '0',
    ])->assertRedirect();

    expect($staff->refresh()->is_active)->toBeFalse();

    // Drop the admin session, then the disabled account cannot authenticate.
    auth()->logout();
    $this->post('/login', ['email' => $staff->email, 'password' => 'secret123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('keeps a password unchanged when the field is left blank on edit', function () {
    $staff = User::factory()->role(UserRole::Sales)->create(['password' => Hash::make('original123')]);

    $this->actingAs($this->admin)->put(route('users.update', $staff), [
        'name' => 'Renamed',
        'email' => $staff->email,
        'role' => UserRole::Sales->value,
        'is_active' => '1',
    ])->assertRedirect();

    expect(Hash::check('original123', $staff->refresh()->password))->toBeTrue()
        ->and($staff->name)->toBe('Renamed');
});

it('assigns additional roles when creating a user', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Priya Support',
        'email' => 'priya-support@neds.test',
        'role' => UserRole::Support->value,
        'additional_roles' => [UserRole::Sales->value],
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_active' => '1',
    ])->assertRedirect(route('users.index'));

    $user = User::firstWhere('email', 'priya-support@neds.test');
    expect($user->hasRole(UserRole::Sales))->toBeTrue()
        ->and($user->allRoles()->pluck('value')->all())->toBe(['support', 'sales']);
});

it('replaces additional roles when editing a user', function () {
    $staff = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();

    $this->actingAs($this->admin)->put(route('users.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'role' => UserRole::Support->value,
        'additional_roles' => [UserRole::Accounts->value],
        'is_active' => '1',
    ])->assertRedirect();

    $staff->refresh();
    expect($staff->hasRole(UserRole::Sales))->toBeFalse()
        ->and($staff->hasRole(UserRole::Accounts))->toBeTrue();
});

it('silently drops an additional role that duplicates the primary role, rather than erroring', function () {
    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Redundant Role',
        'email' => 'redundant@neds.test',
        'role' => UserRole::Sales->value,
        'additional_roles' => [UserRole::Sales->value, UserRole::Support->value],
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_active' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $user = User::firstWhere('email', 'redundant@neds.test');
    expect($user->additionalRoles()->count())->toBe(1)
        ->and($user->hasRole(UserRole::Support))->toBeTrue();
});

it('flushes the menu cache when only additional roles change', function () {
    $staff = User::factory()->role(UserRole::Support)->create();

    Cache::spy();

    $this->actingAs($this->admin)->put(route('users.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'role' => UserRole::Support->value,
        'additional_roles' => [UserRole::Sales->value],
        'is_active' => '1',
    ])->assertRedirect();

    Cache::shouldHaveReceived('forever')->once();
});

it('does not flush the menu cache when nothing role-related changes', function () {
    $staff = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();

    Cache::spy();

    $this->actingAs($this->admin)->put(route('users.update', $staff), [
        'name' => 'Renamed Only',
        'email' => $staff->email,
        'role' => UserRole::Support->value,
        'additional_roles' => [UserRole::Sales->value],
        'is_active' => '1',
    ])->assertRedirect();

    Cache::shouldNotHaveReceived('forever');
});

it('renders the additional roles checkboxes on the create and edit forms', function () {
    $staff = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();

    $this->actingAs($this->admin)->get(route('users.create'))->assertOk()->assertSee('Additional roles');
    $this->actingAs($this->admin)->get(route('users.edit', $staff))->assertOk()->assertSee('Additional roles');
});

it('stops an admin from disabling or deleting their own account', function () {
    // Self-deactivate is ignored.
    $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'role' => UserRole::Sales->value, // attempt to demote self
        'is_active' => '0',               // attempt to disable self
    ])->assertRedirect();

    expect($this->admin->refresh()->is_active)->toBeTrue()
        ->and($this->admin->role)->toBe(UserRole::Admin);

    // Self-delete is blocked.
    $this->actingAs($this->admin)->delete(route('users.destroy', $this->admin))->assertForbidden();
});
