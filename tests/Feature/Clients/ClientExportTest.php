<?php

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets an admin download a CSV of clients', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $client = Customer::factory()->create(['company_name' => 'Acme Digital Pvt Ltd']);

    $response = $this->actingAs($admin)->get(route('clients.export'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $body = $response->streamedContent();
    expect($body)->toContain('Company')
        ->and($body)->toContain('Acme Digital Pvt Ltd');
});

it('forbids a manager from exporting clients — deliberately narrower than every other client capability', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('clients.export'))->assertForbidden();
});

it('forbids sales, support, and accounts from exporting clients', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('clients.export'))->assertForbidden();
})->with([
    'sales' => UserRole::Sales,
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
]);

it('honors the same status filter as the index page', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Customer::factory()->create(['company_name' => 'Active Co', 'status' => CustomerStatus::Active]);
    Customer::factory()->create(['company_name' => 'Inactive Co', 'status' => CustomerStatus::Inactive]);

    $body = $this->actingAs($admin)->get(route('clients.export', ['status' => 'all']))->streamedContent();

    expect($body)->toContain('Active Co')
        ->and($body)->toContain('Inactive Co');

    $bodyDefault = $this->actingAs($admin)->get(route('clients.export'))->streamedContent();

    expect($bodyDefault)->toContain('Active Co')
        ->and($bodyDefault)->not->toContain('Inactive Co');
});
