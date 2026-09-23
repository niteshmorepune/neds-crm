<?php

use App\Enums\OfferKey;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
use Illuminate\Support\Facades\Queue;

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

it('redirects a lead whose own resolved recommendation is NOT the GBP offer straight to that recommendation, not the GBP landing page', function () {
    // Real gap, flagged 2026-09-12: a stale recovery email/WhatsApp link
    // (or any future channel) sent before the funnel was unified across
    // all 4 offers could still send a lead here even though its own
    // goal+budget resolved to a different offer entirely.
    $lead = Lead::factory()->create([
        'recommendation_offer_key' => OfferKey::LeadGenerationAudit->value,
        'recommendation_token' => 'test-token-lga',
    ]);

    $response = $this->get(route('offers.visibility-audit.enter', ['lead' => $lead->id]));

    $response->assertRedirect(route('offers.recommendation', 'test-token-lga'));
    // Still logs the real landing hit against this lead, even though the
    // destination changed — the funnel-tracking purpose is unaffected.
    expect(VisibilityAuditFunnelEvent::first()->lead_id)->toBe($lead->id);
});

it('still redirects to the GBP landing page when the lead\'s own resolved recommendation IS the GBP offer', function () {
    $lead = Lead::factory()->create(['recommendation_offer_key' => OfferKey::GbpAudit->value]);

    $response = $this->get(route('offers.visibility-audit.enter', ['lead' => $lead->id]));

    $response->assertRedirect(route('offers.visibility-audit', ['lead' => $lead->id]));
});

it('redirects to the GBP landing page as before when the lead has no resolved recommendation yet', function () {
    $lead = Lead::factory()->create(['recommendation_offer_key' => null]);

    $response = $this->get(route('offers.visibility-audit.enter', ['lead' => $lead->id]));

    $response->assertRedirect(route('offers.visibility-audit', ['lead' => $lead->id]));
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

it('re-scores an identified lead when it views the landing page', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $lead = Lead::factory()->create();

    Queue::fake();
    $this->get(route('offers.visibility-audit.enter', ['lead' => $lead->id]));

    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->leadId === $lead->id);
});

it('re-scores an identified lead when it reaches checkout', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    config(['services.razorpay.payment_pages.gbp_audit' => 'https://pages.razorpay.com/gbp-audit']);
    $lead = Lead::factory()->create();

    Queue::fake();
    $this->get(route('offers.visibility-audit.checkout', ['tier' => 'gbp', 'lead' => $lead->id]));

    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->leadId === $lead->id);
});

it('does not re-score anything for an anonymous funnel hit with no attributed lead', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);

    Queue::fake();
    $this->get(route('offers.visibility-audit.enter'));

    Queue::assertNothingPushed();
});

it('does not re-score when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $lead = Lead::factory()->create();

    Queue::fake();
    $this->get(route('offers.visibility-audit.enter', ['lead' => $lead->id]));

    Queue::assertNothingPushed();
});
