<?php

use App\Enums\UserRole;
use App\Livewire\ClientServiceLinks;
use App\Models\ClientServiceLink;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a manager add a service link', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['name' => 'GMB']);

    Livewire::actingAs($manager)
        ->test(ClientServiceLinks::class, ['customer' => $customer, 'canManage' => true])
        ->call('newLink')
        ->set('serviceId', $service->id)
        ->set('label', 'GBP Link')
        ->set('url', 'https://business.google.com/acme')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('client_service_links', [
        'customer_id' => $customer->id, 'service_id' => $service->id, 'label' => 'GBP Link',
    ]);
});

it('lets a manager edit and delete a service link', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();
    $link = ClientServiceLink::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'label' => 'Old Label', 'url' => 'https://old.example.com']);

    Livewire::actingAs($manager)
        ->test(ClientServiceLinks::class, ['customer' => $customer, 'canManage' => true])
        ->call('edit', $link->id)
        ->set('label', 'New Label')
        ->call('save');

    expect($link->fresh()->label)->toBe('New Label');

    Livewire::actingAs($manager)
        ->test(ClientServiceLinks::class, ['customer' => $customer, 'canManage' => true])
        ->call('delete', $link->id);

    $this->assertDatabaseMissing('client_service_links', ['id' => $link->id]);
});

it('blocks a role without manageServices from mutating links', function () {
    $intern = User::factory()->role(UserRole::Intern)->create();
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();

    Livewire::actingAs($intern)
        ->test(ClientServiceLinks::class, ['customer' => $customer, 'canManage' => false])
        ->set('serviceId', $service->id)
        ->set('label', 'Blocked')
        ->set('url', 'https://blocked.example.com')
        ->call('save')
        ->assertForbidden();
});

it('groups links by service on the client page', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['name' => 'SEO']);
    ClientServiceLink::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'label' => 'Search Console', 'url' => 'https://search.google.com/console']);

    $this->actingAs($manager)->get(route('clients.show', $customer))->assertOk()
        ->assertSee('Service Links')
        ->assertSee('Search Console');
});
