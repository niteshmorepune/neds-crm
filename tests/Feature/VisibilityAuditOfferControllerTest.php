<?php

it('renders the offer page without requiring login', function () {
    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Your Special Offer')
        ->assertSee('₹120')
        ->assertSee('₹240')
        ->assertSee('₹360');
});

it('hides a tier CTA when its Razorpay Payment Page URL is not configured', function () {
    config([
        'services.razorpay.payment_pages.gbp_audit' => null,
        'services.razorpay.payment_pages.website_audit' => null,
        'services.razorpay.payment_pages.both_audit' => null,
    ]);

    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Coming soon');
});

it('links each CTA to its configured Razorpay Payment Page URL', function () {
    config([
        'services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit',
        'services.razorpay.payment_pages.website_audit' => 'https://pages.razorpay.com/website-audit',
        'services.razorpay.payment_pages.both_audit' => 'https://pages.razorpay.com/both-audit',
    ]);

    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('https://pages.razorpay.com/gbp-audit', false)
        ->assertSee('https://pages.razorpay.com/website-audit', false)
        ->assertSee('https://pages.razorpay.com/both-audit', false);
});
