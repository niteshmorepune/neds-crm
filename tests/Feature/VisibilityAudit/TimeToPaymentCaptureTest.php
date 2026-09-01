<?php

use App\Enums\VisibilityAuditFunnelEventType;
use App\Jobs\RecordVisibilityAuditPurchase;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake([ScoreLead::class]);
    $this->metrics = app(VisibilityAuditFunnelMetrics::class);
});

function recordPurchase(string $paymentId, ?string $phone = '+919876543210'): void
{
    (new RecordVisibilityAuditPurchase(
        paymentId: $paymentId,
        orderId: 'order_'.$paymentId,
        amountPaise: 999900,
        phone: $phone,
        email: 'payer@example.test',
        name: 'Test Payer',
    ))->handle();
}

it('stores time_to_payment_minutes as the delta between the lead\'s first tracked landing view and the payment', function () {
    $lead = Lead::factory()->create(['phone' => '+919876543210']);
    $landingView = VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::LandingViewed,
        'lead_id' => $lead->id,
    ]);
    $landingView->forceFill(['created_at' => Carbon::parse('2026-09-01 10:00:00')])->saveQuietly();

    Carbon::setTestNow(Carbon::parse('2026-09-01 10:45:00'));
    recordPurchase('pay_tp1');
    Carbon::setTestNow();

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_tp1')->first();
    expect($purchase->time_to_payment_minutes)->toBe(45);
});

it('uses the FIRST landing view, not the most recent, when there were repeat visits', function () {
    $lead = Lead::factory()->create(['phone' => '+919876543210']);
    foreach (['2026-09-01 09:00:00', '2026-09-01 09:30:00', '2026-09-01 09:50:00'] as $timestamp) {
        $event = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);
        $event->forceFill(['created_at' => Carbon::parse($timestamp)])->saveQuietly();
    }

    Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
    recordPurchase('pay_tp2');
    Carbon::setTestNow();

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_tp2')->first();
    expect($purchase->time_to_payment_minutes)->toBe(60); // from 09:00, not 09:50
});

it('is null, not zero or an error, when the matched lead has no tracked landing view at all', function () {
    Lead::factory()->create(['phone' => '+919876543210']);

    recordPurchase('pay_tp3');

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_tp3')->first();
    expect($purchase->time_to_payment_minutes)->toBeNull();
});

it('is null for a brand-new lead created at the moment of payment (no prior funnel history is possible)', function () {
    recordPurchase('pay_tp4', phone: '+919999999999'); // no existing Lead with this phone

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_tp4')->first();
    expect($purchase->lead_id)->not->toBeNull()
        ->and($purchase->time_to_payment_minutes)->toBeNull();
});

it('does not confuse a different lead\'s landing view with this one', function () {
    $lead = Lead::factory()->create(['phone' => '+919876543210']);
    $otherLead = Lead::factory()->create();
    $otherEvent = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $otherLead->id]);
    $otherEvent->forceFill(['created_at' => Carbon::parse('2020-01-01 00:00:00')])->saveQuietly();

    recordPurchase('pay_tp5');

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_tp5')->first();
    expect($purchase->lead_id)->toBe($lead->id)
        ->and($purchase->time_to_payment_minutes)->toBeNull(); // this lead itself has no landing view
});

describe('VisibilityAuditFunnelMetrics::landingViewCount()', function () {
    it('counts every repeat visit to the offer landing page', function () {
        $lead = Lead::factory()->create();
        VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);
        VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);
        VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);
        // A checkout ("payment_viewed") hit should not be counted as a landing view.
        VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $lead->id]);

        expect($this->metrics->landingViewCount($lead))->toBe(3);
    });

    it('returns zero for a lead with no tracked landing views', function () {
        $lead = Lead::factory()->create();

        expect($this->metrics->landingViewCount($lead))->toBe(0);
    });
});
