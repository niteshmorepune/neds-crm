<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('sorts clients by company name A-Z when sort=name', function () {
    Customer::factory()->create(['company_name' => 'Zebra Traders']);
    Customer::factory()->create(['company_name' => 'Acme Digital']);
    Customer::factory()->create(['company_name' => 'Mango Media']);

    $this->actingAs($this->admin)
        ->get(route('clients.index', ['sort' => 'name', 'status' => 'all']))
        ->assertOk()
        ->assertSeeInOrder(['Acme Digital', 'Mango Media', 'Zebra Traders']);
});

it('sorts clients by date of entry, oldest first, when sort=oldest', function () {
    $newest = Customer::factory()->create(['company_name' => 'Newest Co']);
    $oldest = Customer::factory()->create(['company_name' => 'Oldest Co']);
    $middle = Customer::factory()->create(['company_name' => 'Middle Co']);

    $oldest->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();
    $middle->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();
    $newest->forceFill(['created_at' => now()])->saveQuietly();

    $this->actingAs($this->admin)
        ->get(route('clients.index', ['sort' => 'oldest', 'status' => 'all']))
        ->assertOk()
        ->assertSeeInOrder(['Oldest Co', 'Middle Co', 'Newest Co']);
});

it('defaults to newest-first when no sort is given', function () {
    $older = Customer::factory()->create(['company_name' => 'Older Co']);
    $newer = Customer::factory()->create(['company_name' => 'Newer Co']);

    $older->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();
    $newer->forceFill(['created_at' => now()])->saveQuietly();

    $this->actingAs($this->admin)
        ->get(route('clients.index', ['status' => 'all']))
        ->assertOk()
        ->assertSeeInOrder(['Newer Co', 'Older Co']);
});

it('sorts clients by location (state, then city, then name) when sort=location', function () {
    Customer::factory()->create(['company_name' => 'Pune Second', 'state' => 'Maharashtra', 'city' => 'Pune']);
    Customer::factory()->create(['company_name' => 'Delhi Co', 'state' => 'Delhi', 'city' => 'New Delhi']);
    Customer::factory()->create(['company_name' => 'Pune First', 'state' => 'Maharashtra', 'city' => 'Mumbai']);

    $this->actingAs($this->admin)
        ->get(route('clients.index', ['sort' => 'location', 'status' => 'all']))
        ->assertOk()
        // Delhi sorts before Maharashtra; within Maharashtra, Mumbai before Pune.
        ->assertSeeInOrder(['Delhi Co', 'Pune First', 'Pune Second']);
});

it('filters clients by state', function () {
    Customer::factory()->create(['company_name' => 'MH Client', 'state' => 'Maharashtra']);
    Customer::factory()->create(['company_name' => 'KA Client', 'state' => 'Karnataka']);

    $this->actingAs($this->admin)
        ->get(route('clients.index', ['state' => 'Karnataka', 'status' => 'all']))
        ->assertOk()
        ->assertSee('KA Client')
        ->assertDontSee('MH Client');
});

it('filters clients by city', function () {
    Customer::factory()->create(['company_name' => 'Pune Client', 'city' => 'Pune']);
    Customer::factory()->create(['company_name' => 'Mumbai Client', 'city' => 'Mumbai']);

    $this->actingAs($this->admin)
        ->get(route('clients.index', ['city' => 'Mumbai', 'status' => 'all']))
        ->assertOk()
        ->assertSee('Mumbai Client')
        ->assertDontSee('Pune Client');
});

it('only lists states/cities actually present on a visible client in the filter dropdowns', function () {
    Customer::factory()->create(['state' => 'Maharashtra', 'city' => 'Pune']);

    $this->actingAs($this->admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Maharashtra')
        ->assertSee('Pune')
        ->assertDontSee('Tamil Nadu');
});

it('scopes a sales rep\'s state/city filter options to their own visible clients only', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();

    Customer::factory()->ownedBy($sales->id)->create(['state' => 'Maharashtra']);
    Customer::factory()->ownedBy($otherSales->id)->create(['state' => 'Karnataka']);

    $this->actingAs($sales)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Maharashtra')
        ->assertDontSee('Karnataka');
});
