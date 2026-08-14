<?php

it('renders the offer page without requiring login', function () {
    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Google Business Profile Audit')
        ->assertSee('₹120');
});

it('shows "Coming soon" when the GBP Razorpay Payment Page URL is not configured', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => null]);

    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Coming soon');
});

it('links every CTA to the configured GBP Razorpay Payment Page URL', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);

    $response = $this->get(route('offers.visibility-audit'))->assertOk();

    $response->assertSee('https://pages.razorpay.com/gbp-audit', false);

    // Hero, buy box, final section, and sticky mobile bar all link to it.
    expect(substr_count($response->getContent(), 'https://pages.razorpay.com/gbp-audit'))->toBe(4);
});
