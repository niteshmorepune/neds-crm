<?php

use App\Enums\LeadSource;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.visibility_audit_first_invite_template_name' => 'va_first_invite',
    ]);
    $this->gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);

    // LeadObserver dispatches ScoreLead/SyncLeadToWadeskJob/
    // SendTelegramLeadAlertJob/SendVisibilityAuditFirstInviteJob on every
    // Lead::factory()->create() below (QUEUE_CONNECTION=sync in tests, so
    // these would otherwise really run) — faking the queue keeps job
    // execution isolated to the explicit ->handle() calls in the tests
    // that actually mean to test the job, and keeps the observer tests
    // scoped to what got pushed rather than what a real send did.
    Queue::fake();
});

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('sends the first-invite template with the enter link and marks the lead invited', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create([
        'name' => 'Priya Shah',
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    Http::assertSent(function ($request) use ($lead) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request['phone'] === '919876543210'
            && $request['templateName'] === 'va_first_invite'
            && $request['variables'] === ['Priya Shah']
            && $request['buttonUrlParam'] === (string) $lead->id;
    });

    expect($lead->fresh()->visibility_audit_invited_at)->not->toBeNull();
});

it('never invites the same lead twice', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
        'visibility_audit_invited_at' => now(),
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('skips sending when the lead paid after the job was queued but before it ran', function () {
    // Regression test for a real incident (lead 242 "Shahdatta Mukadam",
    // 2026-08-19): this job is dispatched once, right when the lead becomes
    // eligible, but runs later on the database queue — real time passes
    // before handle() actually executes, long enough for the lead to
    // independently pay in between. He paid at 06:37:07 and this job fired
    // 4 seconds later at 06:37:11, sending a "come check out our offer"
    // invite right after his own payment-confirmation message.
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);
    VisibilityAuditPurchase::create([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_invite_race1',
        'lead_id' => $lead->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    Http::assertNothingSent();
    expect($lead->fresh()->visibility_audit_invited_at)->toBeNull();
});

it('no-ops when the invite template is not configured', function () {
    config(['services.wadesk.visibility_audit_first_invite_template_name' => null]);
    Http::fake();

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    Http::assertNothingSent();
    expect($lead->fresh()->visibility_audit_invited_at)->toBeNull();
});

it('no-ops when the lead has no phone', function () {
    Http::fake();

    $lead = Lead::factory()->create([
        'phone' => null,
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    Http::assertNothingSent();
});

// ──────────────────────────────────────────────────────────────────────────────
// Observer: which new leads actually get invited
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches the first-invite job for a new lead with a meta_leadgen_id tagged the GMB service', function () {
    Queue::fake();

    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch the first-invite job for a meta_leadgen_id lead tagged a different service', function () {
    Queue::fake();
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);

    Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $seo->id,
    ]);

    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

it('does not dispatch the first-invite job for a lead with no meta_leadgen_id, even if tagged GMB', function () {
    Queue::fake();

    Lead::factory()->create([
        'source' => LeadSource::Website,
        'meta_leadgen_id' => null,
        'service_id' => $this->gmb->id,
    ]);

    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

it('does not dispatch the first-invite job for a meta_leadgen_id lead with no service tagged', function () {
    Queue::fake();

    Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => null,
    ]);

    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

/**
 * Real production lead (id 225): Meta auto-sends a WhatsApp message on the
 * submitter's behalf, which often reaches the CRM before the Lead Ads
 * webhook — so the Lead is created via WhatsApp (source=whatsapp, no
 * meta_leadgen_id, no service_id yet), and (in real production traffic)
 * ImportMetaLead::attachToExistingLead() backfills meta_leadgen_id +
 * service_id + a corrected source onto it a moment later via a plain
 * update(), never re-triggering created(). This test simulates only the
 * meta_leadgen_id/service_id backfill directly (not going through
 * ImportMetaLead, which has its own dedicated source-correction tests in
 * ImportMetaLeadJobTest) to isolate that eligibility is gated on
 * meta_leadgen_id and does not depend on source at all — so a bare
 * update() that never touches source still dispatches the invite.
 */
it('dispatches the first-invite job when meta_leadgen_id and service_id are backfilled onto an existing WhatsApp-sourced lead', function () {
    Queue::fake();

    $lead = Lead::factory()->create([
        'source' => LeadSource::Whatsapp,
        'meta_leadgen_id' => null,
        'service_id' => null,
    ]);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);

    $lead->update(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch again on an unrelated update once already eligible', function () {
    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);
    Queue::fake();

    $lead->update(['next_follow_up_at' => now()->addDay()]);

    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Funnel summary
// ──────────────────────────────────────────────────────────────────────────────

it('summarizes the funnel stage by stage for the GMB meta_leadgen_id cohort only', function () {
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);

    // Eligible + invited + clicked through + paid.
    $wentAllTheWay = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $wentAllTheWay->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $wentAllTheWay->id]);
    VisibilityAuditPurchase::create(['tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000, 'razorpay_payment_id' => 'pay_funnel1', 'lead_id' => $wentAllTheWay->id]);

    // Eligible + invited, never clicked. Deliberately source=whatsapp (not
    // MetaAds) to also prove the funnel report doesn't rely on source.
    Lead::factory()->create(['source' => LeadSource::Whatsapp, 'meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => now()]);

    // Eligible, not yet invited.
    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id, 'visibility_audit_invited_at' => null]);

    // Not eligible — different service, must not count toward this cohort
    // even though it has its own funnel events.
    $offTarget = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $seo->id]);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $offTarget->id]);

    $summary = app(VisibilityAuditFunnelMetrics::class)->funnelSummary();

    expect($summary)->toBe([
        'eligible' => 3,
        'invited' => 2,
        'landing_viewed' => 1,
        'checkout_viewed' => 1,
        'paid' => 1,
    ]);
});

it('bounds the funnel summary by lead creation date when a range is given', function () {
    Queue::fake();
    $old = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    $old->created_at = now()->subDays(30);
    $old->saveQuietly();

    Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    $summary = app(VisibilityAuditFunnelMetrics::class)->funnelSummary(now()->subDays(7), now());

    expect($summary['eligible'])->toBe(1);
});
