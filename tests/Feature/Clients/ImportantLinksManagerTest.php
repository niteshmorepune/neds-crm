<?php

use App\Enums\LinkDepartment;
use App\Enums\LinkPurpose;
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
    // manageLinks() is unrestricted for Admin/Manager/Sales/Support (any
    // client, no ownership check) — Accounts is one of the roles it
    // deliberately excludes.
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    Livewire::actingAs($accounts)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => false])
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
        ->get(route('resources.index'))
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

it('adds a link with a department and purpose', function () {
    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newLink')
        ->set('label', 'Support Ticket SLA doc')
        ->set('url', 'https://docs.example.com/sla')
        ->set('department', LinkDepartment::Support->value)
        ->set('purpose', LinkPurpose::TeamReference->value)
        ->call('save')
        ->assertHasNoErrors();

    $link = ImportantLink::firstWhere('label', 'Support Ticket SLA doc');
    expect($link->department)->toBe(LinkDepartment::Support)
        ->and($link->purpose)->toBe(LinkPurpose::TeamReference);
});

it('leaves department and purpose null (Uncategorized) when not set', function () {
    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newLink')
        ->set('label', 'No category')
        ->set('url', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    $link = ImportantLink::firstWhere('label', 'No category');
    expect($link->department)->toBeNull()
        ->and($link->purpose)->toBeNull();
});

it('rejects a department or purpose value outside the bounded enum', function () {
    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newLink')
        ->set('label', 'Bad category')
        ->set('url', 'https://example.com')
        ->set('department', 'marketing')
        ->set('purpose', 'random')
        ->call('save')
        ->assertHasErrors(['department', 'purpose']);
});

it('an existing link created before this feature stays Uncategorized rather than being backfilled', function () {
    $link = ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Pre-existing link']);

    expect($link->fresh()->department)->toBeNull()
        ->and($link->fresh()->purpose)->toBeNull();
});

it('filters links by department independently of purpose', function () {
    ImportantLink::factory()->department(LinkDepartment::Sales)->create(['customer_id' => $this->customer->id, 'label' => 'Sales Link']);
    ImportantLink::factory()->department(LinkDepartment::Support)->create(['customer_id' => $this->customer->id, 'label' => 'Support Link']);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->set('filterDepartment', LinkDepartment::Sales->value)
        ->assertSee('Sales Link')
        ->assertDontSee('Support Link');
});

it('filters links by purpose independently of department', function () {
    ImportantLink::factory()->purpose(LinkPurpose::ClientSignup)->create(['customer_id' => $this->customer->id, 'label' => 'Signup Link']);
    ImportantLink::factory()->purpose(LinkPurpose::TeamReference)->create(['customer_id' => $this->customer->id, 'label' => 'Reference Link']);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->set('filterPurpose', LinkPurpose::ClientSignup->value)
        ->assertSee('Signup Link')
        ->assertDontSee('Reference Link');
});

it('combines department and purpose filters', function () {
    ImportantLink::factory()->department(LinkDepartment::Sales)->purpose(LinkPurpose::ClientSignup)->create([
        'customer_id' => $this->customer->id, 'label' => 'Sales Signup Link',
    ]);
    ImportantLink::factory()->department(LinkDepartment::Sales)->purpose(LinkPurpose::TeamReference)->create([
        'customer_id' => $this->customer->id, 'label' => 'Sales Reference Link',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->set('filterDepartment', LinkDepartment::Sales->value)
        ->set('filterPurpose', LinkPurpose::ClientSignup->value)
        ->assertSee('Sales Signup Link')
        ->assertDontSee('Sales Reference Link');
});

it('groups the link list by department, with Uncategorized last', function () {
    ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'No Dept Link']);
    ImportantLink::factory()->department(LinkDepartment::Accounts)->create(['customer_id' => $this->customer->id, 'label' => 'Accounts Link']);

    $html = Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->html();

    expect(strpos($html, 'Accounts'))->toBeLessThan(strpos($html, 'Uncategorized'));
});

it('populates department and purpose when editing an existing link', function () {
    $link = ImportantLink::factory()
        ->department(LinkDepartment::Accounts)
        ->purpose(LinkPurpose::InternalToolAccess)
        ->create(['customer_id' => $this->customer->id, 'label' => 'Tally Login']);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('edit', $link->id)
        ->assertSet('department', LinkDepartment::Accounts->value)
        ->assertSet('purpose', LinkPurpose::InternalToolAccess->value);
});

it('hides a role-restricted link from a user without that role', function () {
    $link = ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Support Only Link']);
    $link->syncVisibleRoles([UserRole::Support]);

    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->assertDontSee('Support Only Link');
});

it('shows a role-restricted link to a user who holds that role', function () {
    $link = ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Support Only Link']);
    $link->syncVisibleRoles([UserRole::Support]);

    $support = User::factory()->role(UserRole::Support)->create();

    Livewire::actingAs($support)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => false])
        ->assertSee('Support Only Link');
});

it('always shows a role-restricted link to admin and manager regardless of the restriction', function () {
    $link = ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Support Only Link']);
    $link->syncVisibleRoles([UserRole::Support]);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->assertSee('Support Only Link');
});

it('shows a link with no role restriction to everyone', function () {
    ImportantLink::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Open Link']);

    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->assertSee('Open Link');
});

it('saves visible roles on a new link and restores them when editing', function () {
    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newLink')
        ->set('label', 'Accounts Portal Login')
        ->set('url', 'https://example.com/accounts')
        ->set('visibleRoles', [UserRole::Accounts->value])
        ->call('save')
        ->assertHasNoErrors();

    $link = ImportantLink::firstWhere('label', 'Accounts Portal Login');
    expect($link->visibleRoles->pluck('role')->all())->toBe([UserRole::Accounts]);

    Livewire::actingAs($this->admin)
        ->test(ImportantLinksManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('edit', $link->id)
        ->assertSet('visibleRoles', [UserRole::Accounts->value]);
});

it('shows the Add link button to a support user on the client detail page', function () {
    // 2026-08-04 fix: Links previously reused CustomerPolicy::manage(),
    // which deliberately excludes Support (contacts/notes) — that silently
    // hid the Add link button for Support too, even though the button on
    // this page is driven by canManageLinks(), not canManage().
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)
        ->get(route('clients.show', $this->customer))
        ->assertOk()
        ->assertSee('Add link');
});
