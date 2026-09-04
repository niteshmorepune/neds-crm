<?php

use App\Enums\LeadSource;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Jobs\SendVisibilityAuditFirstInviteEmailJob;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
use App\Mail\VisibilityAuditFirstInviteEmail;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1', 'messageId' => 'wamsg_123'], 201)]);

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

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)
        ->and($touch->touch_type)->toBe(VisibilityAuditTouchType::FirstInvite)
        ->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiWhatsapp)
        ->and($touch->actor_user_id)->toBeNull()
        ->and($touch->success)->toBeTrue()
        ->and($touch->meta['wadesk_message_id'])->toBe('wamsg_123');
});

it('logs a failed touch, not a silently dropped one, when wadesk.in returns non-2xx', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'bad'], 500)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)
        ->and($touch->touch_type)->toBe(VisibilityAuditTouchType::FirstInvite)
        ->and($touch->success)->toBeFalse();
    expect($lead->fresh()->visibility_audit_invited_at)->toBeNull();
});

it('logs a failed touch when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    expect(VisibilityAuditTouch::where('success', false)->count())->toBe(1);
});

it('logs a skipped (not successful) touch and still marks the lead invited when wadesk.in skips an opted-out contact', function () {
    // Same wadesk.in opt-out fix as SendVisibilityAuditRecoveryNudgeJob
    // (real incident, 2026-08-31): /api/send-template returns 200
    // {skipped:true} instead of actually sending a MARKETING template to an
    // opted-out contact -- must not be logged as a successful touch, but the
    // lead still needs marking invited so it isn't retried on a future sweep.
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['skipped' => true, 'reason' => 'opted_out'], 200)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->success)->toBeFalse()
        ->and($touch->meta['skipped'] ?? null)->toBeTrue()
        ->and($touch->meta['reason'] ?? null)->toBe('opted_out');
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

it('skips the WhatsApp first-invite when staff has already replied to the lead over WhatsApp', function () {
    // Real production incident, 2026-08-28 (lead "rocksangli27" / Sanjay
    // Suryavanshi): a lead already mid-conversation with a human-sent
    // proposal (a real call had happened, staff was messaging directly)
    // still got this cold "come check out our offer" invite the moment
    // someone tagged its service GMB. Same class of bug
    // SendVisibilityAuditRecoveryNudgeJob already guards against via
    // Lead::hasStaffWhatsappReplySince() — just never applied here.
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);
    $lead->notes()->create(['body' => "[Sent via WhatsApp by Kiran Katte]\nAs discuss over call please share Business details"]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    Http::assertNothingSent();
    expect($lead->fresh()->visibility_audit_invited_at)->toBeNull();
    expect(VisibilityAuditTouch::count())->toBe(0);
});

it('does not skip the WhatsApp first-invite for the AI after-hours assistant\'s own auto-reply', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);
    $lead->notes()->create(['body' => "[Sent via WhatsApp by AI Assistant (auto-reply)]\nThanks for reaching out, we'll get back to you soon."]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://wadesk.test/api/send-template');
    expect($lead->fresh()->visibility_audit_invited_at)->not->toBeNull();
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

it('logs no touch at all for a guard-clause skip — no touch means no send was ever attempted', function () {
    Http::fake();

    $lead = Lead::factory()->create([
        'phone' => null,
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteJob($lead->id))->handle();

    expect(VisibilityAuditTouch::count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// SendVisibilityAuditFirstInviteEmailJob — email sibling of the WhatsApp job
// ──────────────────────────────────────────────────────────────────────────────

it('sends the first-invite email with the tracked enter link and marks the lead invite-emailed', function () {
    Mail::fake();

    $lead = Lead::factory()->create([
        'name' => 'Priya Shah',
        'email' => 'priya@shah.test',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteEmailJob($lead->id))->handle();

    Mail::assertSent(VisibilityAuditFirstInviteEmail::class, function ($mail) use ($lead) {
        return $mail->hasTo($lead->email) && $mail->lead->is($lead);
    });

    expect($lead->fresh()->visibility_audit_invite_emailed_at)->not->toBeNull();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)
        ->and($touch->touch_type)->toBe(VisibilityAuditTouchType::FirstInvite)
        ->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiEmail)
        ->and($touch->success)->toBeTrue();
});

it('logs a failed touch but does not throw when the first-invite email send fails', function () {
    Mail::shouldReceive('to')->andThrow(new Exception('SMTP down'));

    $lead = Lead::factory()->create([
        'email' => 'priya@shah.test',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    expect(fn () => (new SendVisibilityAuditFirstInviteEmailJob($lead->id))->handle())->not->toThrow(Throwable::class);

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->success)->toBeFalse()->and($touch->channel)->toBe(VisibilityAuditTouchChannel::AiEmail);
    expect($lead->fresh()->visibility_audit_invite_emailed_at)->toBeNull();
});

it('never emails the same lead twice for the first invite', function () {
    Mail::fake();

    $lead = Lead::factory()->create([
        'email' => 'priya@shah.test',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
        'visibility_audit_invite_emailed_at' => now(),
    ]);

    (new SendVisibilityAuditFirstInviteEmailJob($lead->id))->handle();

    Mail::assertNothingSent();
});

it('skips the first-invite email when the lead paid after the job was queued but before it ran', function () {
    Mail::fake();

    $lead = Lead::factory()->create([
        'email' => 'priya@shah.test',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);
    VisibilityAuditPurchase::create([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_invite_email_race1',
        'lead_id' => $lead->id,
    ]);

    (new SendVisibilityAuditFirstInviteEmailJob($lead->id))->handle();

    Mail::assertNothingSent();
    expect($lead->fresh()->visibility_audit_invite_emailed_at)->toBeNull();
});

it('skips the first-invite email when staff has already replied to the lead over WhatsApp', function () {
    // Email sibling of the WhatsApp guard above — same 2026-08-28 incident.
    Mail::fake();

    $lead = Lead::factory()->create([
        'email' => 'priya@shah.test',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);
    $lead->notes()->create(['body' => "[Sent via WhatsApp by Kiran Katte]\nAs discuss over call please share Business details"]);

    (new SendVisibilityAuditFirstInviteEmailJob($lead->id))->handle();

    Mail::assertNothingSent();
    expect($lead->fresh()->visibility_audit_invite_emailed_at)->toBeNull();
});

it('no-ops the first-invite email when the lead has no email', function () {
    Mail::fake();

    $lead = Lead::factory()->create([
        'email' => null,
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    (new SendVisibilityAuditFirstInviteEmailJob($lead->id))->handle();

    Mail::assertNothingSent();
    expect(VisibilityAuditTouch::count())->toBe(0);
});

it('no-ops the first-invite email when the lead no longer exists', function () {
    Mail::fake();

    (new SendVisibilityAuditFirstInviteEmailJob(999999))->handle();

    Mail::assertNothingSent();
});

it('sets Reply-To the configured company reply-to address, and CCs the lead\'s owner', function () {
    Mail::fake();
    config(['company.reply_to_email' => 'contact@niranjanenterprises.com']);

    $owner = User::factory()->create(['email' => 'kiran@niranjanenterprises.co.in']);
    $lead = Lead::factory()->create([
        'email' => 'priya@shah.test',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
        'owner_id' => $owner->id,
    ]);

    (new SendVisibilityAuditFirstInviteEmailJob($lead->id))->handle();

    Mail::assertSent(VisibilityAuditFirstInviteEmail::class, fn ($mail) => $mail->hasReplyTo('contact@niranjanenterprises.com')
        && $mail->hasCc('kiran@niranjanenterprises.co.in'));
});

it('renders the first-invite email with the tracked enter link', function () {
    $lead = Lead::factory()->create([
        'name' => 'Priya Shah',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    $rendered = (new VisibilityAuditFirstInviteEmail($lead))->render();

    expect($rendered)
        ->toContain('Priya Shah')
        ->toContain(route('offers.visibility-audit.enter', ['lead' => $lead->id]));
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

it('dispatches the first-invite EMAIL job alongside the WhatsApp job for a newly eligible lead', function () {
    Queue::fake();

    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
    ]);

    Queue::assertPushed(SendVisibilityAuditFirstInviteEmailJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('still dispatches the first-invite job when the GMB service has been renamed to "GMB Services"', function () {
    // Real incident, 2026-09-04: the live Service Management UI let an
    // admin rename 'GMB' to 'GMB Services' in production, silently
    // breaking every exact `where('name', 'GMB')` lookup (this observer's
    // own gate AND VisibilityAuditFunnelMetrics's whole eligibility
    // engine) — 13 real leads never got invited. gmbServiceId() now
    // matches both names; this asserts the observer's dispatch gate
    // (which reuses that same method) survives the rename too.
    Queue::fake();
    $this->gmb->update(['name' => 'GMB Services']);

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

/**
 * Real production incident (leads 234, 235, 236 — reported by the owner as
 * "not able to see the template or message sent to whoever filed the lead
 * form"): a lead reaches the CRM via WhatsApp with no service tagged yet, so
 * it's ineligible at creation; a staff member later manually tags it GMB via
 * the Lead edit form (LeadUpdateRequest -> LeadController::payload(), which
 * never casts service_id) while marking it "contacted". That form posts
 * service_id as a string, e.g. "2", not the int Eloquent factories/JSON
 * always produce — every other test in this file (including the backfill
 * test above) passes an already-int $this->gmb->id, so none of them exercise
 * this. sendVisibilityAuditInviteIfEligible() compared with strict ===
 * against Service::value('id') (a real int), so "2" === 2 was false and the
 * invite silently never dispatched — no error, no failed job, nothing in the
 * logs. Fixed by casting Lead.service_id to 'integer' so it's normalized on
 * read regardless of how it was assigned.
 */
it('dispatches the first-invite job when service_id is backfilled as a string, as a real HTML form field posts it', function () {
    Queue::fake();

    $lead = Lead::factory()->create([
        'source' => LeadSource::Whatsapp,
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => null,
    ]);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);

    $lead->update(['service_id' => (string) $this->gmb->id, 'status' => 'contacted']);

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

    expect($summary)->toMatchArray([
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

// ──────────────────────────────────────────────────────────────────────────────
// First-invite sweep — safety net for a one-shot dispatch that silently no-op'd
// ──────────────────────────────────────────────────────────────────────────────

it('surfaces an eligible, uninvited lead older than the wait threshold as pending', function () {
    Queue::fake();

    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    $lead->created_at = now()->subMinutes(15);
    $lead->saveQuietly();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingFirstInvites(now()->subMinutes(10))->pluck('id');

    expect($pending)->toContain($lead->id);
});

it('does not surface an eligible lead still inside the wait threshold', function () {
    Queue::fake();

    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingFirstInvites(now()->subMinutes(10))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('does not surface an already-invited lead', function () {
    Queue::fake();

    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
        'visibility_audit_invited_at' => now(),
    ]);
    $lead->created_at = now()->subMinutes(15);
    $lead->saveQuietly();

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingFirstInvites(now()->subMinutes(10))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('does not surface a lead who already purchased despite never being marked invited', function () {
    Queue::fake();

    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    $lead->created_at = now()->subMinutes(15);
    $lead->saveQuietly();
    VisibilityAuditPurchase::create([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_sweep1',
        'lead_id' => $lead->id,
    ]);

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingFirstInvites(now()->subMinutes(10))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('does not surface a lead staff has already replied to over WhatsApp', function () {
    Queue::fake();

    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    $lead->created_at = now()->subMinutes(15);
    $lead->saveQuietly();
    $lead->notes()->create(['body' => "[Sent via WhatsApp by Kiran Katte]\nAs discuss over call please share Business details"]);

    $pending = app(VisibilityAuditFunnelMetrics::class)->pendingFirstInvites(now()->subMinutes(10))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('dispatches the first-invite job for every pending lead when the sweep command runs', function () {
    Queue::fake();

    $stuck = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);
    $stuck->created_at = now()->subDays(3);
    $stuck->saveQuietly();

    $fresh = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    // Re-fake to discard the creation-time dispatches from LeadObserver above
    // — this test is only about what the sweep command itself pushes.
    Queue::fake();

    Artisan::call('app:send-visibility-audit-first-invite-sweep');

    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $stuck->id);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $fresh->id);
    Queue::assertPushed(SendVisibilityAuditFirstInviteEmailJob::class, fn ($job) => $job->leadId === $stuck->id);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteEmailJob::class, fn ($job) => $job->leadId === $fresh->id);
});
