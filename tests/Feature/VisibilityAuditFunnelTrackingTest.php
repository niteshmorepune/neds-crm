<?php

use App\Enums\VisibilityAuditFunnelEventType;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;

it('logs an anonymous landing-page hit and redirects to the offer page when no lead is given', function () {
    $response = $this->get(route('offers.visibility-audit.enter'));

    $response->assertRedirect(route('offers.visibility-audit'));

    expect(VisibilityAuditFunnelEvent::count())->toBe(1);
    $event = VisibilityAuditFunnelEvent::first();
    expect($event->event_type)->toBe(VisibilityAuditFunnelEventType::LandingViewed);
    expect($event->lead_id)->toBeNull();
});

it('attributes a landing-page hit to a real lead and carries it forward in the redirect', function () {
    $lead = Lead::factory()->create();

    $response = $this->get(route('offers.visibility-audit.enter', ['lead' => $lead->id]));

    $response->assertRedirect(route('offers.visibility-audit', ['lead' => $lead->id]));
    expect(VisibilityAuditFunnelEvent::first()->lead_id)->toBe($lead->id);
});

it('ignores a lead id that does not exist rather than breaking the redirect', function () {
    $response = $this->get(route('offers.visibility-audit.enter', ['lead' => 999999]));

    $response->assertRedirect(route('offers.visibility-audit'));
    expect(VisibilityAuditFunnelEvent::first()->lead_id)->toBeNull();
});

it('logs a payment-page hit and redirects to the configured Razorpay Payment Page for the tier', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);

    $response = $this->get(route('offers.visibility-audit.checkout', ['tier' => 'gbp']));

    $response->assertRedirect('https://pages.razorpay.com/gbp-audit');

    $event = VisibilityAuditFunnelEvent::first();
    expect($event->event_type)->toBe(VisibilityAuditFunnelEventType::PaymentViewed);
    expect($event->tier->value)->toBe('gbp');
});

it('defaults to the gbp tier when none is given', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);

    $this->get(route('offers.visibility-audit.checkout'))
        ->assertRedirect('https://pages.razorpay.com/gbp-audit');

    expect(VisibilityAuditFunnelEvent::first()->tier->value)->toBe('gbp');
});

it('falls back to the offer page instead of breaking when the tier has no configured Payment Page URL', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => null]);

    $this->get(route('offers.visibility-audit.checkout', ['tier' => 'gbp']))
        ->assertRedirect(route('offers.visibility-audit'));

    expect(VisibilityAuditFunnelEvent::count())->toBe(1);
});

it('attributes a payment-page hit to a real lead', function () {
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);
    $lead = Lead::factory()->create();

    $this->get(route('offers.visibility-audit.checkout', ['tier' => 'gbp', 'lead' => $lead->id]))
        ->assertRedirect('https://pages.razorpay.com/gbp-audit');

    expect(VisibilityAuditFunnelEvent::first()->lead_id)->toBe($lead->id);
});
