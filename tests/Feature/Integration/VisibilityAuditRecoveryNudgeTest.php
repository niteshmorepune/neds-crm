<?php

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Jobs\SendVisibilityAuditRecoveryNudgeJob;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.visibility_audit_recovery_landing_template_name' => 'va_recovery_landing',
        'services.wadesk.visibility_audit_recovery_checkout_template_name' => 'va_recovery_checkout',
    ]);

    // LeadObserver dispatches SyncLeadToWadeskJob/SendTelegramLeadAlertJob/
    // ScoreLead on every Lead::factory()->create() below — faking the queue
    // keeps those from actually running (and making their own real HTTP
    // calls that Http::fake() would otherwise record) so assertions here
    // only ever see the recovery-nudge job's own request.
    Queue::fake();
});

function backdateEvent(VisibilityAuditFunnelEvent $event, Carbon $createdAt): VisibilityAuditFunnelEvent
{
    $event->created_at = $createdAt;
    $event->save();

    return $event;
}

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('sends the checkout-stage template with the checkout link and marks the event nudged', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create(['name' => 'Priya Shah', 'phone' => '+91 98765 43210']);
    $event = VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::PaymentViewed,
        'tier' => VisibilityAuditTier::Gbp,
        'lead_id' => $lead->id,
    ]);

    (new SendVisibilityAuditRecoveryNudgeJob($lead->id, $event->id, VisibilityAuditFunnelEventType::PaymentViewed))->handle();

    Http::assertSent(function ($request) use ($lead) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request['phone'] === '919876543210'
            && $request['templateName'] === 'va_recovery_checkout'
            && $request['variables'] === ['Priya Shah']
            && $request['buttonUrlParam'] === (string) $lead->id;
    });

    expect($event->fresh()->nudged_at)->not->toBeNull();
});

it('sends the landing-stage template with the enter link', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create(['phone' => '+91 98765 43210']);
    $event = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);

    (new SendVisibilityAuditRecoveryNudgeJob($lead->id, $event->id, VisibilityAuditFunnelEventType::LandingViewed))->handle();

    Http::assertSent(fn ($request) => $request['templateName'] === 'va_recovery_landing'
        && $request['buttonUrlParam'] === (string) $lead->id);
});

it('never sends twice for the same event once already nudged', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create(['phone' => '+91 98765 43210']);
    $event = VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::PaymentViewed,
        'lead_id' => $lead->id,
        'nudged_at' => now(),
    ]);

    (new SendVisibilityAuditRecoveryNudgeJob($lead->id, $event->id, VisibilityAuditFunnelEventType::PaymentViewed))->handle();

    Http::assertNothingSent();
});

it('no-ops when the template for that stage is not configured', function () {
    config(['services.wadesk.visibility_audit_recovery_checkout_template_name' => null]);
    Http::fake();

    $lead = Lead::factory()->create(['phone' => '+91 98765 43210']);
    $event = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $lead->id]);

    (new SendVisibilityAuditRecoveryNudgeJob($lead->id, $event->id, VisibilityAuditFunnelEventType::PaymentViewed))->handle();

    Http::assertNothingSent();
    expect($event->fresh()->nudged_at)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Metrics: which leads are actually pending
// ──────────────────────────────────────────────────────────────────────────────

it('only surfaces a checkout-stuck lead as pending once past the wait threshold', function () {
    $recent = Lead::factory()->create();
    backdateEvent(VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $recent->id]), now()->subMinutes(10));

    $old = Lead::factory()->create();
    backdateEvent(VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $old->id]), now()->subHours(3));

    $metrics = app(VisibilityAuditFunnelMetrics::class);
    $pending = $metrics->pendingCheckoutNudges(now()->subHours(2))->pluck('id');

    expect($pending)->not->toContain($recent->id);
    expect($pending)->toContain($old->id);
});

it('excludes an already-nudged lead from pending, even past the threshold', function () {
    $lead = Lead::factory()->create();
    $event = VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::PaymentViewed,
        'lead_id' => $lead->id,
        'nudged_at' => now()->subHours(1),
    ]);
    backdateEvent($event, now()->subHours(3));

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingCheckoutNudges(now()->subHours(2))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('excludes a lead who already paid from pending nudges', function () {
    $lead = Lead::factory()->create();
    backdateEvent(VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $lead->id]), now()->subHours(3));
    VisibilityAuditPurchase::create([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_paid1',
        'lead_id' => $lead->id,
    ]);

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingCheckoutNudges(now()->subHours(2))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Command
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches one nudge job per pending lead across both stages', function () {
    Queue::fake();

    $checkoutLead = Lead::factory()->create();
    backdateEvent(VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $checkoutLead->id]), now()->subHours(3));

    $landingLead = Lead::factory()->create();
    backdateEvent(VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $landingLead->id]), now()->subHours(5));

    $tooRecentLead = Lead::factory()->create();
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $tooRecentLead->id]);

    Artisan::call('app:send-visibility-audit-recovery-nudges');

    Queue::assertPushed(SendVisibilityAuditRecoveryNudgeJob::class, 2);
    Queue::assertPushed(fn (SendVisibilityAuditRecoveryNudgeJob $job) => $job->leadId === $checkoutLead->id && $job->stage === VisibilityAuditFunnelEventType::PaymentViewed);
    Queue::assertPushed(fn (SendVisibilityAuditRecoveryNudgeJob $job) => $job->leadId === $landingLead->id && $job->stage === VisibilityAuditFunnelEventType::LandingViewed);
});
