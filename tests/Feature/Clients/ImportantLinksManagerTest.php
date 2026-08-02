<?php

use App\Enums\UserRole;
use App\Livewire\ImportantLinksManager;
use App\Models\Customer;
use App\Models\ImportantLink;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->customer = Customer::factory()->create();
});

it('adds a link to a client', function () {
    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newLink')
        ->set('label', 'Google Business Profile')
        ->set('url', 'https://business.google.com/acme')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->customer->links()->count())->toBe(1)
        ->and(ImportantLink::firstWhere('label', 'Google Business Profile')->customer_id)->toBe($this->customer->id);
});

it('validates label and url are required and url is a real url', function () {
    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newLink')
        ->set('label', '')
        ->set('url', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['label' => 'required', 'url' => 'url']);
});

it('edits a client link', function () {
    $link = ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Old Label']);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('edit', $link->id)
        ->set('label', 'New Label')
        ->call('save')
        ->assertHasNoErrors();

    expect($link->fresh()->label)->toBe('New Label');
});

it('deletes a client link', function () {
    $link = ImportantLink::factory()->create(['customer_id' => $this->customer->id]);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('delete', $link->id);

    expect(ImportantLink::find($link->id))->toBeNull();
});

it('forbids managing a client link without permission', function () {
    // A sales rep who does not own this client cannot manage its links.
    $foreignSales = User::factory()->role(UserRole::Sales)->create();
    $owned = Customer::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();

    Livewire::actingAs($foreignSales)
        ->test(ImportantLinksManager::class, ['customer' => $owned, 'canManage' => false])
        ->call('newLink')
        ->assertForbidden();
});

it('shows a client link on the client detail page for any staff member who can view the client', function () {
    ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Client Drive Folder', 'url' => 'https://drive.google.com/acme']);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $this->customer))
        ->assertOk()
        ->assertSee('Client Drive Folder')
        ->assertSee('Links (1)', false);
});

it('does not leak a link belonging to a different client through the same component instance', function () {
    $otherClient = Customer::factory()->create();
    $otherLink = ImportantLink::factory()->create(['customer_id' => $otherClient->id]);

    expect(fn () => Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('edit', $otherLink->id)
    )->toThrow(ModelNotFoundException::class);
});

it('lets an admin manage the company-wide (global) list', function () {
    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class)
        ->call('newLink')
        ->set('label', 'Hostinger hPanel')
        ->set('url', 'https://hpanel.hostinger.com')
        ->call('save')
        ->assertHasNoErrors();

    $link = ImportantLink::firstWhere('label', 'Hostinger hPanel');
    expect($link)->not->toBeNull()
        ->and($link->customer_id)->toBeNull();
});

it('forbids a non-admin/manager from managing the global list', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(ImportantLinksManager::class)
        ->call('newLink')
        ->assertForbidden();
});

it('lets any authenticated user view the global important links page', function () {
    ImportantLink::factory()->create(['label' => 'Domain Registrar', 'url' => 'https://registrar.example.com']);
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)
        ->get(route('important-links.index'))
        ->assertOk()
        ->assertSee('Domain Registrar')
        ->assertDontSee('Add link');
});

it('does not mix a global link into a client\'s links tab', function () {
    ImportantLink::factory()->create(['label' => 'Global Only Link']);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $this->customer))
        ->assertOk()
        ->assertDontSee('Global Only Link');
});
