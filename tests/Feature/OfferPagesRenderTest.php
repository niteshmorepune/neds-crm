<?php

use App\Models\Lead;
use App\Models\OfferFunnelEvent;

it('renders the lead generation audit page', function () {
    $this->get(route('offers.lead-generation-audit'))
        ->assertOk()
        ->assertSee('Lead Generation Funnel Audit')
        ->assertSee('₹299');
});

it('renders the website growth audit page', function () {
    $this->get(route('offers.website-growth-audit'))
        ->assertOk()
        ->assertSee('Website + Conversion Growth Audit')
        ->assertSee('₹499');
});

it('renders the growth strategy page', function () {
    $this->get(route('offers.growth-strategy'))
        ->assertOk()
        ->assertSee('Personalized Digital Growth Strategy')
        ->assertSee('₹999')
        ->assertSee('₹10,000');
});

it('still renders the pre-existing, untouched GBP visibility audit page at its original price', function () {
    $this->get(route('offers.visibility-audit'))
        ->assertOk()
        ->assertSee('Google Business Profile Audit')
        ->assertSee('₹120');
});

it('shows "Coming soon" on the 3 new offer pages when Razorpay is not configured', function () {
    config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);

    $this->get(route('offers.lead-generation-audit'))->assertOk()->assertSee('Coming soon');
    $this->get(route('offers.website-growth-audit'))->assertOk()->assertSee('Coming soon');
    $this->get(route('offers.growth-strategy'))->assertOk()->assertSee('Coming soon');
});

it('tracks an offer_viewed event and sets offer_viewed_at when a lead id is passed', function () {
    $lead = Lead::factory()->create();

    $this->get(route('offers.lead-generation-audit', ['lead' => $lead->id]))->assertOk();

    expect($lead->fresh()->offer_viewed_at)->not->toBeNull();
    expect(OfferFunnelEvent::where('lead_id', $lead->id)->where('event_type', 'offer_viewed')->exists())->toBeTrue();
});
