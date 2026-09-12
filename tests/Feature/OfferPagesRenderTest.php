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

it('shows the website_url field on the website growth audit page, required, with a required attribute', function () {
    config(['services.razorpay.key_id' => 'rzp_test', 'services.razorpay.key_secret' => 'secret']);

    $response = $this->get(route('offers.website-growth-audit'))->assertOk();

    $response->assertSee('id="website_url"', false);
    $response->assertSee('required', false);
});

it('prefills the website_url field from a matched lead\'s own website_url', function () {
    config(['services.razorpay.key_id' => 'rzp_test', 'services.razorpay.key_secret' => 'secret']);
    $lead = Lead::factory()->create(['website_url' => 'https://existing-site.example.com']);

    $response = $this->get(route('offers.website-growth-audit', ['lead' => $lead->id]))->assertOk();

    $response->assertSee('value="https://existing-site.example.com"', false);
});

it('does not show a website_url field on the other 2 non-GBP offer pages', function () {
    config(['services.razorpay.key_id' => 'rzp_test', 'services.razorpay.key_secret' => 'secret']);

    $this->get(route('offers.lead-generation-audit'))->assertOk()->assertDontSee('id="website_url"', false);
    $this->get(route('offers.growth-strategy'))->assertOk()->assertDontSee('id="website_url"', false);
});

it('prefills the GBP offer page\'s gbp_url field from a matched lead\'s own gbp_url', function () {
    config(['services.razorpay.key_id' => 'rzp_test', 'services.razorpay.key_secret' => 'secret']);
    $lead = Lead::factory()->create(['gbp_url' => 'https://g.co/kgs/existing']);

    $response = $this->get(route('offers.visibility-audit', ['lead' => $lead->id]))->assertOk();

    $response->assertSee('value="https://g.co/kgs/existing"', false);
});
