<?php

use App\Enums\UserRole;
use App\Livewire\ContactsManager;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->customer = Customer::factory()->create();
});

it('adds a contact', function () {
    Livewire::actingAs($this->admin)
        ->test(ContactsManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newContact')
        ->set('name', 'Asha Rao')
        ->set('email', 'asha@acme.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->customer->contacts()->count())->toBe(1)
        ->and(Contact::firstWhere('name', 'Asha Rao')->customer_id)->toBe($this->customer->id);
});

it('validates that a contact name is required', function () {
    Livewire::actingAs($this->admin)
        ->test(ContactsManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newContact')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('enforces a single primary contact', function () {
    $first = Contact::factory()->primary()->create(['customer_id' => $this->customer->id]);

    Livewire::actingAs($this->admin)
        ->test(ContactsManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newContact')
        ->set('name', 'Second Primary')
        ->set('is_primary', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($this->customer->contacts()->where('is_primary', true)->count())->toBe(1)
        ->and($this->customer->primaryContact->name)->toBe('Second Primary');
});

it('can promote an existing contact to primary', function () {
    Contact::factory()->primary()->create(['customer_id' => $this->customer->id]);
    $second = Contact::factory()->create(['customer_id' => $this->customer->id]);

    Livewire::actingAs($this->admin)
        ->test(ContactsManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('makePrimary', $second->id);

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($this->customer->contacts()->where('is_primary', true)->count())->toBe(1);
});

it('deletes a contact', function () {
    $contact = Contact::factory()->create(['customer_id' => $this->customer->id]);

    Livewire::actingAs($this->admin)
        ->test(ContactsManager::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('delete', $contact->id);

    expect(Contact::find($contact->id))->toBeNull();
});

it('forbids managing contacts without permission', function () {
    // A sales rep who does not own this client cannot manage its contacts.
    $foreignSales = User::factory()->role(UserRole::Sales)->create();
    $owned = Customer::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();

    Livewire::actingAs($foreignSales)
        ->test(ContactsManager::class, ['customer' => $owned, 'canManage' => false])
        ->call('newContact')
        ->assertForbidden();
});
