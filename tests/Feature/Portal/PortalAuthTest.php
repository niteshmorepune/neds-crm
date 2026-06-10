<?php

use App\Enums\UserRole;
use App\Livewire\ContactsManager;
use App\Mail\PortalInvitation;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('redirects portal guests to the portal login', function () {
    $this->get(route('portal.home'))->assertRedirect(route('portal.login'));
});

it('lets a portal-enabled contact log in', function () {
    $contact = Contact::factory()->portalUser()->create(['email' => 'client@x.test']);

    $this->post(route('portal.login'), ['email' => 'client@x.test', 'password' => 'password'])
        ->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($contact, 'portal');
});

it('rejects a wrong password', function () {
    Contact::factory()->portalUser()->create(['email' => 'client@x.test']);

    $this->post(route('portal.login'), ['email' => 'client@x.test', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('portal');
});

it('refuses login for a contact without portal access even with the right password', function () {
    Contact::factory()->create([
        'email' => 'noportal@x.test',
        'password' => bcrypt('password'),
        'portal_enabled' => false,
    ]);

    $this->post(route('portal.login'), ['email' => 'noportal@x.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('portal');
});

it('sets a password from a valid invitation token and signs in', function () {
    $contact = Contact::factory()->create(['email' => 'invitee@x.test']);
    $token = $contact->inviteToPortal();

    $this->get(route('portal.password.setup', $token))->assertOk();

    $this->post(route('portal.password.store', $token), [
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($contact->fresh(), 'portal');
    expect($contact->fresh()->password_set_at)->not->toBeNull()
        ->and($contact->fresh()->invitation_token)->toBeNull();
});

it('404s on an invalid invitation token', function () {
    $this->get(route('portal.password.setup', 'bogus-token'))->assertNotFound();
});

it('lets an admin invite a contact to the portal and emails them', function () {
    Mail::fake();
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->create(['customer_id' => $customer->id, 'email' => 'new@x.test']);

    Livewire::actingAs($admin)
        ->test(ContactsManager::class, ['customer' => $customer, 'canManage' => true])
        ->call('invite', $contact->id);

    expect($contact->fresh()->portal_enabled)->toBeTrue();
    Mail::assertSent(PortalInvitation::class, fn (PortalInvitation $m) => $m->hasTo('new@x.test'));
});
