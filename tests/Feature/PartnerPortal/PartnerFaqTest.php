<?php

use App\Models\Partner;

beforeEach(function () {
    $this->partner = Partner::factory()->portalUser()->create();
});

it('renders the partner FAQ for a logged-in partner', function () {
    $this->actingAs($this->partner, 'partner')->get(route('partner-portal.faq'))->assertOk()
        ->assertSee('Frequently Asked Questions')
        ->assertSee('Your Earnings'); // real content, not a blank/placeholder page
});

it('redirects a guest to login instead of showing the FAQ', function () {
    $this->get(route('partner-portal.faq'))->assertRedirect(route('partner-portal.login'));
});

it('shows a FAQ link in the partner portal header', function () {
    $this->actingAs($this->partner, 'partner')->get(route('partner-portal.home'))->assertOk()
        ->assertSee(route('partner-portal.faq'), false);
});
