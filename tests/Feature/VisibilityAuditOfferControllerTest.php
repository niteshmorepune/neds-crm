<?php

it('renders the offer page without requiring login', function () {
    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Special Offer')
        ->assertSee('Google Visibility Check')
        ->assertSee('₹120');
});

it('shows "Coming soon" when the GBP Razorpay Payment Page URL is not configured', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => null]);

    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Coming soon');
});

it('links the CTA to the configured GBP Razorpay Payment Page URL', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);

    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('https://pages.razorpay.com/gbp-audit', false);
});
