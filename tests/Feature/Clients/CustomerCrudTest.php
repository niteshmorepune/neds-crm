<?php

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('creates a client with valid data and derives the state name', function () {
    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Acme Digital Pvt Ltd',
            'gstin' => '27ABCDE1234F1Z5',
            'email' => 'hello@acme.test',
            'state_code' => '27',
            'tags' => 'seo, retainer',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertRedirect();

    $customer = Customer::firstWhere('company_name', 'Acme Digital Pvt Ltd');

    expect($customer)->not->toBeNull()
        ->and($customer->state)->toBe('Maharashtra')
        ->and($customer->state_code)->toBe('27')
        ->and($customer->tags)->toBe(['seo', 'retainer']);
});

it('rejects an invalid GSTIN', function () {
    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Bad GST Co',
            'gstin' => 'INVALID12345678',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertSessionHasErrors('gstin');

    expect(Customer::where('company_name', 'Bad GST Co')->exists())->toBeFalse();
});

it('rejects a duplicate GSTIN', function () {
    Customer::factory()->create(['gstin' => '27ABCDE1234F1Z5']);

    $this->actingAs($this->admin)
        ->post(route('clients.store'), [
            'company_name' => 'Dup Co',
            'gstin' => '27ABCDE1234F1Z5',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertSessionHasErrors('gstin');
});

it('requires a company name', function () {
    $this->actingAs($this->admin)
        ->post(route('clients.store'), ['status' => CustomerStatus::Active->value])
        ->assertSessionHasErrors('company_name');
});

it('updates a client', function () {
    $customer = Customer::factory()->create(['company_name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->put(route('clients.update', $customer), [
            'company_name' => 'New Name',
            'status' => CustomerStatus::Inactive->value,
        ])
        ->assertRedirect(route('clients.show', $customer));

    expect($customer->fresh())
        ->company_name->toBe('New Name')
        ->status->toBe(CustomerStatus::Inactive);
});

it('soft deletes a client', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('clients.destroy', $customer))
        ->assertRedirect(route('clients.index'));

    $this->assertSoftDeleted($customer);
});
