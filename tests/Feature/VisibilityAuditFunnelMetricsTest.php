<?php

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
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
// totalPurchases() / purchasesQuery() — unscoped by Meta attribution
// ──────────────────────────────────────────────────────────────────────────────

it('totalPurchases() counts every purchase in the window, including one with no Meta attribution', function () {
    $metaLead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_total_a', 'lead_id' => $metaLead->id]);

    $otherLead = Lead::factory()->create(['meta_leadgen_id' => null]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_total_b', 'lead_id' => $otherLead->id]);

    expect($this->metrics->totalPurchases())->toBe(2)
        ->and($this->metrics->funnelSummary()['paid'])->toBe(1);
});

it('purchasesQuery() respects the from/to window and orders latest first', function () {
    $inWindow = VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_win_a']);
    $outOfWindow = VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_win_b']);
    $outOfWindow->forceFill(['created_at' => now()->subDays(90)])->saveQuietly();

    $results = $this->metrics->purchasesQuery(now()->subDays(2), now())->get();

    expect($results->pluck('id')->all())->toBe([$inWindow->id]);
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

it('excludes a purchase from a non-eligible (no meta_leadgen_id) lead from the daily "paid" series, matching funnelSummary()\'s tile', function () {
    // Real incident, 2026-08-27: the daily trend's "Paid" series had no
    // eligibility scoping at all, so a purchase from a lead with no Meta
    // attribution (e.g. a direct/organic checkout) inflated it past the
    // funnelSummary() tile's count on the same dashboard (8 vs 6).
    $from = now()->subDays(2)->startOfDay();
    $to = now()->endOfDay();

    $eligible = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_elig1', 'lead_id' => $eligible->id]);

    $notEligible = Lead::factory()->create(['meta_leadgen_id' => null, 'service_id' => $this->gmb->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_noelig1', 'lead_id' => $notEligible->id]);

    $trend = $this->metrics->trend($from, $to);
    $totalPaidInTrend = collect($trend)->sum('paid');

    $summary = $this->metrics->funnelSummary($from, $to);

    expect($totalPaidInTrend)->toBe(1)
        ->and($totalPaidInTrend)->toBe($summary['paid']);
});

// ──────────────────────────────────────────────────────────────────────────────
// leadsForStage()
// ──────────────────────────────────────────────────────────────────────────────

it('returns the actual leads behind each stage count', function () {
    $paid = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $paid->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $paid->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_stage1', 'lead_id' => $paid->id]);

    $invitedOnly = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);
    $notInvited = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    Lead::factory()->create(); // out of cohort entirely

    expect($this->metrics->leadsForStage('eligible')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$paid->id, $invitedOnly->id, $notInvited->id])->sort()->values()->all())
        ->and($this->metrics->leadsForStage('invited')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$paid->id, $invitedOnly->id])->sort()->values()->all())
        ->and($this->metrics->leadsForStage('landing_viewed')->pluck('id')->all())
        ->toBe([$paid->id])
        ->and($this->metrics->leadsForStage('checkout_viewed')->pluck('id')->all())
        ->toBe([$paid->id])
        ->and($this->metrics->leadsForStage('paid')->pluck('id')->all())
        ->toBe([$paid->id]);
});

it('throws on an unknown stage key', function () {
    $this->metrics->leadsForStage('bogus');
})->throws(InvalidArgumentException::class);

// ──────────────────────────────────────────────────────────────────────────────
// notYetInvitedCount() / failedTouchesCount() / touchLogQuery()
// ──────────────────────────────────────────────────────────────────────────────

it('counts eligible leads with no invite sent yet', function () {
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    expect($this->metrics->notYetInvitedCount())->toBe(2);
});

it('counts failed AI-WhatsApp send attempts, excluding staff-call and successful rows', function () {
    $lead = Lead::factory()->create();

    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => false]);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::RecoveryNudgeLanding, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::ManualOutreach, 'channel' => VisibilityAuditTouchChannel::StaffCall, 'occurred_at' => now(), 'success' => false]);

    expect($this->metrics->failedTouchesCount())->toBe(1);
});

it('lists AI-WhatsApp touches with lead attached, filterable by type and outcome', function () {
    $lead = Lead::factory()->create(['name' => 'Touch Log Lead']);

    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::RecoveryNudgeLanding, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => false, 'meta' => ['error' => 'timeout']]);
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::ManualOutreach, 'channel' => VisibilityAuditTouchChannel::StaffCall, 'occurred_at' => now(), 'success' => true]);

    $all = $this->metrics->touchLogQuery()->get();
    expect($all)->toHaveCount(2)
        ->and($all->first()->lead->name)->toBe('Touch Log Lead');

    $failedOnly = $this->metrics->touchLogQuery(null, null, null, false)->get();
    expect($failedOnly)->toHaveCount(1)
        ->and($failedOnly->first()->meta['error'])->toBe('timeout');

    $firstInviteOnly = $this->metrics->touchLogQuery(null, null, VisibilityAuditTouchType::FirstInvite)->get();
    expect($firstInviteOnly)->toHaveCount(1);
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

it('treats a lead with a purchase as in-cohort, even without meta_leadgen_id or a funnel event', function () {
    // Real incident, 2026-08-27: RecordVisibilityAuditPurchase auto-creates
    // a bare Lead (no meta_leadgen_id, no funnel event) when a payer's
    // phone matches no existing open Lead — that lead was previously
    // invisible to funnelStatusFor(), so staff had no way to act on a real,
    // paying customer.
    $lead = Lead::factory()->create(['meta_leadgen_id' => null]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_bare_'.uniqid(), 'lead_id' => $lead->id]);

    expect($this->metrics->isVisibilityAuditCohort($lead))->toBeTrue()
        ->and($this->metrics->funnelStatusFor($lead))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// funnelStatusFor()
// ──────────────────────────────────────────────────────────────────────────────

it('returns null for a lead outside the VA cohort', function () {
    $lead = Lead::factory()->create();

    expect($this->metrics->funnelStatusFor($lead))->toBeNull();
});

it('reports "eligible, not yet invited" for a cohort lead with no invite sent', function () {
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    expect($this->metrics->funnelStatusFor($lead))
        ->toBe(['stage' => 'eligible', 'label' => 'Eligible for the Visibility Audit invite, not yet sent', 'tone' => 'gray', 'since' => null]);
});

it('reports "invited" once invited_at is set but no funnel event exists yet', function () {
    $invitedAt = now()->subHour();
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => $invitedAt]);

    $status = $this->metrics->funnelStatusFor($lead);

    expect($status['stage'])->toBe('invited')
        ->and($status['since']->toDateTimeString())->toBe($invitedAt->toDateTimeString());
});

it('reports the checkout stage over the landing stage when a lead reached both', function () {
    $lead = Lead::factory()->create();
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $lead->id]);

    expect($this->metrics->funnelStatusFor($lead)['stage'])->toBe('checkout_stuck');
});

it('reports "paid" even for a lead who also has a checkout event, since paid outranks everything', function () {
    $lead = Lead::factory()->create();
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $lead->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_status1', 'lead_id' => $lead->id]);

    expect($this->metrics->funnelStatusFor($lead)['stage'])->toBe('paid');
});

// ──────────────────────────────────────────────────────────────────────────────
// leadsAwaitingServiceTag()
// ──────────────────────────────────────────────────────────────────────────────

it('returns the actual leads awaiting a service tag, oldest first', function () {
    $newer = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null, 'name' => 'Newer']);
    $newer->forceFill(['created_at' => now()->subHour()])->saveQuietly();

    $older = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null, 'name' => 'Older']);
    $older->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]); // tagged — excluded

    expect($this->metrics->leadsAwaitingServiceTag()->pluck('name')->all())->toBe(['Older', 'Newer']);
});

// ──────────────────────────────────────────────────────────────────────────────
// afterHoursTouches()
// ──────────────────────────────────────────────────────────────────────────────

it('keeps only touches that occurred outside 9am-7pm Asia/Kolkata', function () {
    $lead = Lead::factory()->create();
    $tz = 'Asia/Kolkata';

    $late = VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    $late->forceFill(['occurred_at' => now($tz)->setTime(22, 0)->utc()])->saveQuietly();

    $daytime = VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::RecoveryNudgeLanding, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    $daytime->forceFill(['occurred_at' => now($tz)->setTime(14, 0)->utc()])->saveQuietly();

    $tooOld = VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);
    $tooOld->forceFill(['occurred_at' => now($tz)->subDays(5)->setTime(23, 0)->utc()])->saveQuietly();

    $result = $this->metrics->afterHoursTouches(now()->subHours(24));

    expect($result->pluck('id')->all())->toBe([$late->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// unansweredInboundReplies()
// ──────────────────────────────────────────────────────────────────────────────

it('flags a lead whose customer reply has had no staff WhatsApp reply since', function () {
    $lead = Lead::factory()->create();
    $touch = VisibilityAuditTouch::create([
        'lead_id' => $lead->id,
        'touch_type' => VisibilityAuditTouchType::CustomerReply,
        'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp,
        'occurred_at' => now()->subHours(3),
        'success' => true,
    ]);

    $result = $this->metrics->unansweredInboundReplies(now()->subHours(2));

    expect($result->pluck('lead_id')->all())->toBe([$lead->id])
        ->and($result->first()->id)->toBe($touch->id);
});

it('excludes a lead whose customer reply already got a staff WhatsApp reply', function () {
    $lead = Lead::factory()->create();
    VisibilityAuditTouch::create([
        'lead_id' => $lead->id,
        'touch_type' => VisibilityAuditTouchType::CustomerReply,
        'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp,
        'occurred_at' => now()->subHours(3),
        'success' => true,
    ]);
    $lead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nOn it, calling you now."]);

    expect($this->metrics->unansweredInboundReplies(now()->subHours(2)))->toHaveCount(0);
});

it('does not count the AI after-hours holding reply as staff having answered', function () {
    $lead = Lead::factory()->create();
    VisibilityAuditTouch::create([
        'lead_id' => $lead->id,
        'touch_type' => VisibilityAuditTouchType::CustomerReply,
        'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp,
        'occurred_at' => now()->subHours(3),
        'success' => true,
    ]);
    $lead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by AI Assistant (auto-reply)]\nThanks, someone will get back to you."]);

    expect($this->metrics->unansweredInboundReplies(now()->subHours(2)))->toHaveCount(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// $ownerId scoping — stuckAtLanding/stuckAtCheckout/leadsAwaitingServiceTag/
// unansweredInboundReplies/touchLogQuery (Recovery worklist "Your gaps"/
// "Your message log" sections)
// ──────────────────────────────────────────────────────────────────────────────

it('scopes stuckAtCheckout/stuckAtLanding to one owner when $ownerId is given', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mineCheckout = Lead::factory()->create(['owner_id' => $owner->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $mineCheckout->id]);
    $theirsCheckout = Lead::factory()->create(['owner_id' => $other->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $theirsCheckout->id]);

    $mineLanding = Lead::factory()->create(['owner_id' => $owner->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $mineLanding->id]);
    $theirsLanding = Lead::factory()->create(['owner_id' => $other->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $theirsLanding->id]);

    expect($this->metrics->stuckAtCheckout($owner->id)->pluck('id')->all())->toBe([$mineCheckout->id])
        ->and($this->metrics->stuckAtLanding($owner->id)->pluck('id')->all())->toBe([$mineLanding->id]);
});

it('scopes leadsAwaitingServiceTag to one owner when $ownerId is given', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = Lead::factory()->create(['owner_id' => $owner->id, 'meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null]);
    Lead::factory()->create(['owner_id' => $other->id, 'meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null]);

    expect($this->metrics->leadsAwaitingServiceTag(10, $owner->id)->pluck('id')->all())->toBe([$mine->id]);
});

it('scopes unansweredInboundReplies to one owner when $ownerId is given', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = Lead::factory()->create(['owner_id' => $owner->id]);
    VisibilityAuditTouch::create(['lead_id' => $mine->id, 'touch_type' => VisibilityAuditTouchType::CustomerReply, 'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp, 'occurred_at' => now()->subHours(3), 'success' => true]);

    $theirs = Lead::factory()->create(['owner_id' => $other->id]);
    VisibilityAuditTouch::create(['lead_id' => $theirs->id, 'touch_type' => VisibilityAuditTouchType::CustomerReply, 'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp, 'occurred_at' => now()->subHours(3), 'success' => true]);

    expect($this->metrics->unansweredInboundReplies(now()->subHours(2), $owner->id)->pluck('lead_id')->all())->toBe([$mine->id]);
});

it('scopes touchLogQuery to one owner when $ownerId is given', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = Lead::factory()->create(['owner_id' => $owner->id]);
    VisibilityAuditTouch::create(['lead_id' => $mine->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);

    $theirs = Lead::factory()->create(['owner_id' => $other->id]);
    VisibilityAuditTouch::create(['lead_id' => $theirs->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite, 'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true]);

    $result = $this->metrics->touchLogQuery(ownerId: $owner->id)->get();

    expect($result->pluck('lead_id')->all())->toBe([$mine->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// needsFollowUp()
// ──────────────────────────────────────────────────────────────────────────────

it('needsFollowUp excludes a stuck lead already replied to by staff since its latest event', function () {
    $handled = Lead::factory()->create();
    $event = VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $handled->id]);
    $event->forceFill(['created_at' => now()->subHours(5)])->saveQuietly();
    $handled->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nOn it."]);

    $untouched = Lead::factory()->create();
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $untouched->id]);

    $stuck = $this->metrics->stuckAtLanding();
    $result = $this->metrics->needsFollowUp($stuck);

    expect($result->pluck('id')->all())->toBe([$untouched->id]);
});

it('only reports the latest unanswered reply per lead, not every historical one', function () {
    $lead = Lead::factory()->create();
    VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::CustomerReply, 'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp, 'occurred_at' => now()->subDays(2), 'success' => true]);
    $latest = VisibilityAuditTouch::create(['lead_id' => $lead->id, 'touch_type' => VisibilityAuditTouchType::CustomerReply, 'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp, 'occurred_at' => now()->subHours(3), 'success' => true]);

    $result = $this->metrics->unansweredInboundReplies(now()->subHours(2));

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($latest->id);
});
