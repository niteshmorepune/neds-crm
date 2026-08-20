<?php

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // LeadObserver dispatches several jobs (ScoreLead, SyncLeadToWadeskJob,
    // SendVisibilityAuditFirstInviteJob, ...) on every eligible
    // Lead::factory()->create() below — fake the queue so this file's
    // assertions only ever see what the test itself created.
    Queue::fake();
    $this->gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $this->metrics = app(VisibilityAuditFunnelMetrics::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// funnelSummary() — conversion percentages
// ──────────────────────────────────────────────────────────────────────────────

it('computes stage-to-stage and overall conversion percentages', function () {
    $paid = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $paid->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $paid->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_pct1', 'lead_id' => $paid->id]);

    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    $summary = $this->metrics->funnelSummary();

    expect($summary['eligible'])->toBe(4)
        ->and($summary['invited'])->toBe(3)
        ->and($summary['invited_pct'])->toBe(75.0)
        ->and($summary['landing_pct'])->toBe(round(1 / 3 * 100, 1))
        ->and($summary['checkout_pct'])->toBe(100.0)
        ->and($summary['paid_pct'])->toBe(100.0)
        ->and($summary['overall_pct'])->toBe(25.0);
});

it('returns null (not zero or a division error) for a percentage whose denominator stage is empty', function () {
    $summary = $this->metrics->funnelSummary();

    expect($summary['eligible'])->toBe(0)
        ->and($summary['invited_pct'])->toBeNull()
        ->and($summary['landing_pct'])->toBeNull()
        ->and($summary['checkout_pct'])->toBeNull()
        ->and($summary['paid_pct'])->toBeNull()
        ->and($summary['overall_pct'])->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// trend()
// ──────────────────────────────────────────────────────────────────────────────

it('buckets funnel activity by Asia/Kolkata calendar day, not UTC', function () {
    $tz = 'Asia/Kolkata';

    // 23:30 IST on day 1 is 18:00 UTC the same day — safely inside day 1
    // either way, so this only proves the trend responds to real data at
    // all; the real timezone-safety assertion is the boundary case below.
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    $lead->created_at = now($tz)->subDays(2)->setTime(23, 30)->utc();
    $lead->saveQuietly();

    $trend = $this->metrics->trend(now($tz)->subDays(5)->startOfDay()->utc(), now($tz)->endOfDay()->utc());

    $day = now($tz)->subDays(2)->toDateString();
    $bucket = collect($trend)->firstWhere('label', now($tz)->subDays(2)->format('d M'));

    expect($bucket)->not->toBeNull()->and($bucket['eligible'])->toBe(1);
});

it('does not mis-bucket a lead created just after IST midnight into the previous UTC day', function () {
    // 00:30 IST is 19:00 UTC the PREVIOUS calendar day — a UTC-bucketed
    // trend would put this in "yesterday"; it must land in "today" (IST).
    $tz = 'Asia/Kolkata';
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    $lead->created_at = now($tz)->startOfDay()->addMinutes(30)->utc();
    $lead->saveQuietly();

    $trend = $this->metrics->trend(now($tz)->subDays(3)->startOfDay()->utc(), now($tz)->endOfDay()->utc());

    $today = collect($trend)->firstWhere('label', now($tz)->format('d M'));
    $yesterday = collect($trend)->firstWhere('label', now($tz)->subDay()->format('d M'));

    expect($today['eligible'])->toBe(1)
        ->and($yesterday['eligible'])->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// touchesByChannel() / conversionByChannel()
// ──────────────────────────────────────────────────────────────────────────────

it('counts touches per channel within a window', function () {
    $lead = Lead::factory()->create();

    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::RecoveryNudgeLanding, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::ManualOutreach, 'channel' => VisibilityAuditTouchChannel::StaffCall, 'occurred_at' => now(), 'success' => true]);
    // Outside the window — must not count.
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now()->subDays(10), 'success' => true]);

    $result = $this->metrics->touchesByChannel(now()->subDay(), now()->addDay());

    expect($result)->toBe(['ai_whatsapp' => 2, 'staff_call' => 1]);
});

it('credits a purchase as staff-assisted only when the staff touch happened before the purchase', function () {
    $staffAssistedLead = Lead::factory()->create();
    VisibilityAuditTouch::create(['lead_id' => $staffAssistedLead->id, 'touch_type' => VisibilityAuditTouchType::ManualOutreach, 'channel' => VisibilityAuditTouchChannel::StaffCall, 'occurred_at' => now()->subHour(), 'success' => true]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_assisted1', 'lead_id' => $staffAssistedLead->id]);

    $aiOnlyLead = Lead::factory()->create();
    VisibilityAuditTouch::create(['lead_id' => $aiOnlyLead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now()->subHour(), 'success' => true]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_aionly1', 'lead_id' => $aiOnlyLead->id]);

    // A staff call that happened AFTER the purchase must not count as assisted.
    $lateStaffTouchLead = Lead::factory()->create();
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_late1', 'lead_id' => $lateStaffTouchLead->id]);
    VisibilityAuditTouch::create(['lead_id' => $lateStaffTouchLead->id, 'touch_type' => VisibilityAuditTouchType::ManualOutreach, 'channel' => VisibilityAuditTouchChannel::StaffCall, 'occurred_at' => now()->addHour(), 'success' => true]);

    // Anonymous purchase (no lead_id) — excluded entirely.
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_anon1']);

    $result = $this->metrics->conversionByChannel();

    expect($result)->toBe([
        'staff_assisted' => 1,
        'ai_only' => 2,
        'total' => 3,
        'staff_assisted_pct' => round(1 / 3 * 100, 1),
    ]);
});

it('returns a null percentage, not a division error, when nobody paid yet', function () {
    expect($this->metrics->conversionByChannel())->toBe([
        'staff_assisted' => 0,
        'ai_only' => 0,
        'total' => 0,
        'staff_assisted_pct' => null,
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// awaitingServiceTag()
// ──────────────────────────────────────────────────────────────────────────────

it('counts Meta leads with no service tagged yet, and excludes tagged/non-Meta leads', function () {
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    Lead::factory()->create(['meta_leadgen_id' => null, 'service_id' => null]);

    expect($this->metrics->awaitingServiceTag())->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// isVisibilityAuditCohort()
// ──────────────────────────────────────────────────────────────────────────────

it('treats a meta_leadgen_id lead tagged GMB as in-cohort', function () {
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    expect($this->metrics->isVisibilityAuditCohort($lead))->toBeTrue();
});

it('treats a lead with an existing funnel event as in-cohort, even without meta_leadgen_id or GMB', function () {
    $lead = Lead::factory()->create();
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);

    expect($this->metrics->isVisibilityAuditCohort($lead))->toBeTrue();
});

it('treats an unrelated lead as out of cohort', function () {
    $lead = Lead::factory()->create();

    expect($this->metrics->isVisibilityAuditCohort($lead))->toBeFalse();
});
