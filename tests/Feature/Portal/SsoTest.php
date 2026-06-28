<?php

use App\Models\Contact;
use App\Models\Customer;
use App\Support\PortalSsoToken;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    config(['services.portal_sso.secret' => 'test-sso-secret-32-chars-minimum!!']);
});

// ─── PortalSsoToken unit tests ────────────────────────────────────────────────

it('generates a three-part JWT', function () {
    $contact  = Contact::factory()->create(['email' => 'alice@example.com', 'name' => 'Alice']);
    $customer = Customer::factory()->create(['drishti_client_id' => 'd-1', 'smdost_client_id' => 's-1']);

    $token  = PortalSsoToken::generate($contact, $customer);
    $parts  = explode('.', $token);

    expect($parts)->toHaveCount(3);
});

it('verifies a freshly generated token and returns the claims', function () {
    $contact  = Contact::factory()->create(['email' => 'alice@example.com', 'name' => 'Alice']);
    $customer = Customer::factory()->create(['drishti_client_id' => 'd-1', 'smdost_client_id' => 's-1']);

    $token  = PortalSsoToken::generate($contact, $customer);
    $claims = PortalSsoToken::verify($token);

    expect($claims)->not->toBeNull()
        ->and($claims['email'])->toBe('alice@example.com')
        ->and($claims['drishti_client_id'])->toBe('d-1')
        ->and($claims['smdost_client_id'])->toBe('s-1');
});

it('rejects a token with a tampered signature', function () {
    $contact  = Contact::factory()->create(['email' => 'alice@example.com']);
    $customer = Customer::factory()->create();

    $token  = PortalSsoToken::generate($contact, $customer);
    $tampered = $token . 'x';

    expect(PortalSsoToken::verify($tampered))->toBeNull();
});

it('rejects an expired token', function () {
    $contact  = Contact::factory()->create(['email' => 'alice@example.com']);
    $customer = Customer::factory()->create();

    $this->travelTo(now()->subMinutes(20));
    $token = PortalSsoToken::generate($contact, $customer);
    $this->travelBack();

    expect(PortalSsoToken::verify($token))->toBeNull();
});

it('rejects a token signed with a different secret', function () {
    $contact  = Contact::factory()->create(['email' => 'alice@example.com']);
    $customer = Customer::factory()->create();

    config(['services.portal_sso.secret' => 'wrong-secret']);
    $token = PortalSsoToken::generate($contact, $customer);
    config(['services.portal_sso.secret' => 'test-sso-secret-32-chars-minimum!!']);

    expect(PortalSsoToken::verify($token))->toBeNull();
});

// ─── SsoController redirect tests ────────────────────────────────────────────

it('redirects to drishti with a token in the URL', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => 'd-abc']);
    $contact  = Contact::factory()->create(['customer_id' => $customer->id, 'email' => 'bob@example.com']);

    $response = $this->actingAs($contact, 'portal')->get(route('portal.sso', 'drishti'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/api/sso?token=');
});

it('redirects to smdost with a token in the URL', function () {
    $customer = Customer::factory()->create(['smdost_client_id' => 's-abc']);
    $contact  = Contact::factory()->create(['customer_id' => $customer->id, 'email' => 'carol@example.com']);

    $response = $this->actingAs($contact, 'portal')->get(route('portal.sso', 'smdost'));

    expect($response->headers->get('Location'))->toContain('/sso?token=');
});

it('redirects back with error when customer has no drishti_client_id', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => null]);
    $contact  = Contact::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.sso', 'drishti'))
        ->assertRedirect(route('portal.home'));
});

it('redirects back with error when customer has no smdost_client_id', function () {
    $customer = Customer::factory()->create(['smdost_client_id' => null]);
    $contact  = Contact::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.sso', 'smdost'))
        ->assertRedirect(route('portal.home'));
});

it('returns 404 for an unknown app name', function () {
    $customer = Customer::factory()->create();
    $contact  = Contact::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.sso', 'unknown-app'))
        ->assertNotFound();
});

it('requires portal authentication', function () {
    $this->get(route('portal.sso', 'drishti'))->assertRedirect(route('portal.login'));
});
