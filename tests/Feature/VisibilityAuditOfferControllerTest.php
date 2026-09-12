<?php

use App\Models\Lead;

it('renders the offer page without requiring login', function () {
    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Google Business Profile Audit')
        ->assertSee('₹120');
});

it('shows "Coming soon" when Razorpay is not configured', function () {
    config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);

    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Coming soon');
});

it('renders the in-app checkout script and gbp_url field when Razorpay is configured', function () {
    config(['services.razorpay.key_id' => 'rzp_test_123', 'services.razorpay.key_secret' => 'secret']);

    $response = $this->get(route('offers.visibility-audit'))->assertOk();

    $response->assertSee('gbp_url', false);
    $response->assertSee(route('offers.visibility-audit.order'), false);
    $response->assertSee(route('offers.visibility-audit.verify'), false);
});

it('carries a lead reference through to the checkout script when present', function () {
    config(['services.razorpay.key_id' => 'rzp_test_123', 'services.razorpay.key_secret' => 'secret']);
    $lead = Lead::factory()->create();

    $response = $this->get(route('offers.visibility-audit', ['lead' => $lead->id]))->assertOk();

    $response->assertSee("leadId: {$lead->id}", false);
});
