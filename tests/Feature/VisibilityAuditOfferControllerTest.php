<?php

use App\Models\Lead;

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

it('links every CTA to the funnel-tracking checkout redirect, not the raw Razorpay URL', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);

    $response = $this->get(route('offers.visibility-audit'))->assertOk();

    $checkoutUrl = route('offers.visibility-audit.checkout', ['tier' => 'gbp']);
    $response->assertSee($checkoutUrl, false);
    $response->assertDontSee('https://pages.razorpay.com/gbp-audit', false);

    // Hero, buy box, final section, and sticky mobile bar all link to it.
    expect(substr_count($response->getContent(), $checkoutUrl))->toBe(4);
});

it('carries a lead reference through to every checkout CTA when present', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);
    $lead = Lead::factory()->create();

    $response = $this->get(route('offers.visibility-audit', ['lead' => $lead->id]))->assertOk();

    $checkoutUrl = htmlspecialchars(route('offers.visibility-audit.checkout', ['tier' => 'gbp', 'lead' => $lead->id]));
    expect(substr_count($response->getContent(), $checkoutUrl))->toBe(4);
});
