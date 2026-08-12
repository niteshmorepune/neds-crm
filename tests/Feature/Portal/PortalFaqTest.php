<?php

use App\Models\Contact;
use App\Models\Customer;

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->contact = Contact::factory()->portalUser()->create(['customer_id' => $this->customer->id]);
});

it('renders the client FAQ for a logged-in portal contact', function () {
    $this->actingAs($this->contact, 'portal')->get(route('portal.faq'))->assertOk()
        ->assertSee('Frequently Asked Questions')
        ->assertSee('Pay Now'); // real content, not a blank/placeholder page
});

it('redirects a guest to login instead of showing the FAQ', function () {
    $this->get(route('portal.faq'))->assertRedirect(route('portal.login'));
});

it('shows a FAQ link in the portal sidebar', function () {
    $this->actingAs($this->contact, 'portal')->get(route('portal.home'))->assertOk()
        ->assertSee(route('portal.faq'), false);
});
