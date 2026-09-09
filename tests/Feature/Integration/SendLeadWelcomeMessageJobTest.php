<?php

use App\Enums\LeadSource;
use App\Jobs\SendLeadWelcomeMessageJob;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.lead_welcome_template_name' => 'lead_welcome',
    ]);
    $this->gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);

    // Same isolation reasoning as VisibilityAuditFirstInviteTest -- keeps
    // job execution scoped to the explicit ->handle() calls below.
    Queue::fake();
});

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('sends the welcome template with the lead\'s name and service, and marks it sent', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1', 'messageId' => 'wamsg_1'], 201)]);
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);

    $lead = Lead::factory()->create([
        'name' => 'Priya Shah',
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $seo->id,
    ]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request['phone'] === '919876543210'
            && $request['templateName'] === 'lead_welcome'
            && $request['variables'] === ['Priya Shah', 'SEO', 'Priya Shah', 'SEO'];
    });

    expect($lead->fresh()->welcome_message_sent_at)->not->toBeNull();
    $note = $lead->notes()->latest()->first();
    expect($note->body)->toContain('Automated welcome message sent via WhatsApp');
});

it('falls back to "your enquiry" when the lead has no service tagged', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => null,
    ]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    Http::assertSent(fn ($request) => $request['variables'][1] === 'your enquiry' && $request['variables'][3] === 'your enquiry');
});

it('does not send twice for the same lead', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'welcome_message_sent_at' => now(),
    ]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('still marks the lead sent, without a note, when wadesk.in skips an opted-out contact', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['skipped' => true, 'reason' => 'opted_out'], 200)]);

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
    ]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    expect($lead->fresh()->welcome_message_sent_at)->not->toBeNull()
        ->and($lead->notes()->count())->toBe(0);
});

it('does not mark the lead sent when wadesk.in returns non-2xx', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'bad'], 500)]);

    $lead = Lead::factory()->create(['phone' => '+91 98765 43210', 'meta_leadgen_id' => 'lg_'.uniqid()]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    expect($lead->fresh()->welcome_message_sent_at)->toBeNull();
});

it('does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);
    $lead = Lead::factory()->create(['phone' => '+91 98765 43210', 'meta_leadgen_id' => 'lg_'.uniqid()]);

    expect(fn () => (new SendLeadWelcomeMessageJob($lead->id))->handle())->not->toThrow(Throwable::class);
    expect($lead->fresh()->welcome_message_sent_at)->toBeNull();
});

it('skips when staff has already replied to the lead over WhatsApp', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create(['phone' => '+91 98765 43210', 'meta_leadgen_id' => 'lg_'.uniqid()]);
    $lead->notes()->create(['body' => "[Sent via WhatsApp by Kiran Katte]\nAlready spoke on the phone."]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    Http::assertNothingSent();
    expect($lead->fresh()->welcome_message_sent_at)->toBeNull();
});

it('no-ops when the template is not configured', function () {
    config(['services.wadesk.lead_welcome_template_name' => null]);
    Http::fake();
    $lead = Lead::factory()->create(['phone' => '+91 98765 43210', 'meta_leadgen_id' => 'lg_'.uniqid()]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('no-ops when the lead has no phone', function () {
    Http::fake();
    $lead = Lead::factory()->create(['phone' => null, 'meta_leadgen_id' => 'lg_'.uniqid()]);

    (new SendLeadWelcomeMessageJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('no-ops when the lead no longer exists', function () {
    Http::fake();

    (new SendLeadWelcomeMessageJob(999999))->handle();

    Http::assertNothingSent();
});

// ──────────────────────────────────────────────────────────────────────────────
// LeadObserver dispatch
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches the welcome job for a Meta Ads lead with no service tagged', function () {
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => null]);

    Queue::assertPushed(SendLeadWelcomeMessageJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('dispatches the welcome job for a Meta Ads lead tagged a non-GMB service', function () {
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $seo->id]);

    Queue::assertPushed(SendLeadWelcomeMessageJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('dispatches the VA first-invite job, not the generic welcome, for a GMB-tagged Meta Ads lead', function () {
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('does not dispatch the welcome job for a non-Meta lead', function () {
    Lead::factory()->create(['meta_leadgen_id' => null, 'source' => LeadSource::Website]);

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('dispatches the welcome job when meta_leadgen_id is backfilled via update (the WhatsApp-race scenario)', function () {
    $lead = Lead::factory()->create(['meta_leadgen_id' => null, 'source' => LeadSource::Whatsapp]);
    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);

    $lead->update(['meta_leadgen_id' => 'lg_'.uniqid()]);

    Queue::assertPushed(SendLeadWelcomeMessageJob::class, fn ($job) => $job->leadId === $lead->id);
});
