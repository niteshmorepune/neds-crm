<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets an admin download a CSV of leads', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['name' => 'Priya Sharma', 'company' => 'Sharma Traders']);

    $response = $this->actingAs($admin)->get(route('leads.export'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $body = $response->streamedContent();
    expect($body)->toContain('Name')
        ->and($body)->toContain('Priya Sharma')
        ->and($body)->toContain('Sharma Traders');
});

it('forbids a manager from exporting leads — deliberately narrower than every other lead capability', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('leads.export'))->assertForbidden();
});

it('forbids sales, support, and telecaller from exporting leads', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('leads.export'))->assertForbidden();
})->with([
    'sales' => UserRole::Sales,
    'support' => UserRole::Support,
    'telecaller' => UserRole::Telecaller,
]);

it('honors the same status filter as the index page', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Lead::factory()->create(['name' => 'New Lead Co', 'status' => LeadStatus::New]);
    Lead::factory()->create(['name' => 'Lost Lead Co', 'status' => LeadStatus::Lost]);

    $body = $this->actingAs($admin)->get(route('leads.export', ['status' => 'lost']))->streamedContent();

    expect($body)->toContain('Lost Lead Co')
        ->and($body)->not->toContain('New Lead Co');
});
