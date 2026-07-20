<?php

use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets an admin view the subscriptions list but forbids a manager', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get(route('subscriptions.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('subscriptions.index'))->assertForbidden();
});

it('renders the create and edit pages for an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($admin)->get(route('subscriptions.create'))->assertOk();
    $this->actingAs($admin)->get(route('subscriptions.edit', $subscription))->assertOk();
});

it('creates a subscription, converting the rupee cost input to paise', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('subscriptions.store'), [
        'name' => 'Claude Subscription',
        'vendor' => 'Anthropic',
        'cost' => '2000',
        'billing_cycle' => RecurringFrequency::Monthly->value,
        'renewal_date' => now()->addMonth()->toDateString(),
        'reminder_days_before' => 7,
    ])->assertRedirect(route('subscriptions.index'));

    $subscription = Subscription::firstWhere('name', 'Claude Subscription');
    expect($subscription)->not->toBeNull()
        ->and($subscription->cost)->toBe(Money::toPaise(2000.0))
        ->and($subscription->is_active)->toBeFalse(); // checkbox not sent on the add form
});

it('updates a subscription', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $subscription = Subscription::factory()->create(['name' => 'Old name']);

    $this->actingAs($admin)->put(route('subscriptions.update', $subscription), [
        'name' => 'New name',
        'vendor' => $subscription->vendor,
        'cost' => Money::toRupees($subscription->cost),
        'billing_cycle' => $subscription->billing_cycle->value,
        'renewal_date' => $subscription->renewal_date->toDateString(),
        'reminder_days_before' => $subscription->reminder_days_before,
        'is_active' => '1',
    ])->assertRedirect(route('subscriptions.index'));

    expect($subscription->fresh()->name)->toBe('New name');
});

it('deletes a subscription', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($admin)->delete(route('subscriptions.destroy', $subscription))->assertRedirect();

    expect(Subscription::find($subscription->id))->toBeNull();
});

it('forbids a manager from creating, editing or deleting a subscription', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($manager)->post(route('subscriptions.store'), ['name' => 'Sneaky'])->assertForbidden();
    $this->actingAs($manager)->put(route('subscriptions.update', $subscription), ['name' => 'Sneaky'])->assertForbidden();
    $this->actingAs($manager)->delete(route('subscriptions.destroy', $subscription))->assertForbidden();
});

it('rejects a bad billing_cycle value', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('subscriptions.store'), [
        'name' => 'Bad',
        'cost' => '100',
        'billing_cycle' => 'weekly',
        'renewal_date' => now()->addMonth()->toDateString(),
        'reminder_days_before' => 7,
    ])->assertSessionHasErrors('billing_cycle');
});
