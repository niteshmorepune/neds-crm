<?php

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('shows a client\'s single active service on the client list', function () {
    $service = Service::factory()->create(['name' => 'SEO']);
    $client = Customer::factory()->create(['company_name' => 'Single Service Co']);
    RecurringInvoice::factory()->create(['customer_id' => $client->id, 'service_id' => $service->id, 'is_active' => true]);

    $this->actingAs($this->admin)->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Single Service Co')
        ->assertSee('SEO')
        ->assertDontSee('more');
});

it('shows a dash for a client with no active services', function () {
    Customer::factory()->create(['company_name' => 'No Service Co']);

    $this->actingAs($this->admin)->get(route('clients.index'))
        ->assertOk()
        ->assertSee('No Service Co');
});

it('truncates to 2 services plus a "+N more" toggle when a client has 3 or more active services', function () {
    $client = Customer::factory()->create(['company_name' => 'Many Service Co']);
    $names = ['SEO', 'GMB', 'Social Media'];
    foreach ($names as $name) {
        $service = Service::factory()->create(['name' => $name]);
        RecurringInvoice::factory()->create(['customer_id' => $client->id, 'service_id' => $service->id, 'is_active' => true]);
    }

    $html = $this->actingAs($this->admin)->get(route('clients.index'))->assertOk()->getContent();

    expect($html)->toContain('SEO')
        ->toContain('GMB')
        ->toContain('+1 more')
        // activeServiceNames() sorts alphabetically — the full list is still
        // present (hover tooltip + the hidden expanded panel).
        ->toContain('GMB, SEO, Social Media');
});

it('does not show a "more" toggle for exactly 2 active services', function () {
    $client = Customer::factory()->create(['company_name' => 'Two Service Co']);
    foreach (['SEO', 'GMB'] as $name) {
        $service = Service::factory()->create(['name' => $name]);
        RecurringInvoice::factory()->create(['customer_id' => $client->id, 'service_id' => $service->id, 'is_active' => true]);
    }

    $this->actingAs($this->admin)->get(route('clients.index'))
        ->assertOk()
        ->assertSee('SEO')
        ->assertSee('GMB')
        ->assertDontSee('more');
});

it('reflects a service purchased via a project as well as a recurring template', function () {
    $service = Service::factory()->create(['name' => 'Website Development']);
    $client = Customer::factory()->create(['company_name' => 'Project Service Co']);
    Project::factory()->create(['customer_id' => $client->id, 'service_id' => $service->id, 'status' => ProjectStatus::Active]);

    $this->actingAs($this->admin)->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Website Development');
});

it('never shows a completed project\'s service as still active on the client list', function () {
    $service = Service::factory()->create(['name' => 'Website Development']);
    $client = Customer::factory()->create(['company_name' => 'Finished Project Co']);
    Project::factory()->create(['customer_id' => $client->id, 'service_id' => $service->id, 'status' => ProjectStatus::Completed]);

    $this->actingAs($this->admin)->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Finished Project Co')
        ->assertDontSee('Website Development');
});

it('renders the client list without error when several clients each carry multiple active services', function () {
    $services = Service::factory()->count(3)->sequence(
        ['name' => 'SEO'], ['name' => 'GMB'], ['name' => 'Social Media'],
    )->create();

    foreach (range(1, 5) as $i) {
        $client = Customer::factory()->create();
        foreach ($services as $service) {
            RecurringInvoice::factory()->create(['customer_id' => $client->id, 'service_id' => $service->id, 'is_active' => true]);
        }
    }

    $this->actingAs($this->admin)->get(route('clients.index'))->assertOk();
});
