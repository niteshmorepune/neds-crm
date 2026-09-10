<?php

use App\Enums\LeadStatus;
use App\Jobs\SendLeadWelcomeMessageJob;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // LeadObserver dispatches SyncLeadToWadeskJob/SendTelegramLeadAlertJob/
    // ScoreLead/SendLeadWelcomeMessageJob on every Lead::factory()->create()
    // below -- faking the queue keeps those from actually running, so
    // assertions here only ever see this command's own dispatches.
    Queue::fake();
});

function metaLead(array $attributes = []): Lead
{
    return Lead::factory()->create(array_merge(['meta_leadgen_id' => 'lg_'.uniqid()], $attributes));
}

function failWelcome(Lead $lead, int $times = 1, ?Carbon $at = null): Lead
{
    $lead->forceFill(['welcome_message_sent_at' => null, 'welcome_message_wadesk_id' => null])->save();

    // Note.created_at isn't fillable -- create() always stamps real wall-clock
    // "now", so backdating needs an explicit forceFill()->save() afterward
    // (same gotcha documented on DraftDealStallFollowUpsCommandTest's own
    // backdatedDeal() helper).
    for ($i = 0; $i < $times; $i++) {
        $note = $lead->notes()->create([
            'user_id' => null,
            'body' => '❌ Welcome WhatsApp message failed to deliver: In order to maintain a healthy ecosystem engagement, the message failed to be delivered.',
        ]);
        if ($at !== null) {
            $note->forceFill(['created_at' => $at])->save();
        }
    }

    return $lead->fresh();
}

it('retries a lead whose welcome failed past the backoff window', function () {
    $lead = metaLead();
    failWelcome($lead, at: now()->subHours(3));

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertPushed(SendLeadWelcomeMessageJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not retry before the backoff window has passed', function () {
    $lead = metaLead();
    failWelcome($lead, at: now()->subMinutes(30));

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('does not retry a lead that has never actually been attempted', function () {
    metaLead(['welcome_message_sent_at' => null]);

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('does not retry a lead whose welcome already succeeded', function () {
    $lead = metaLead(['welcome_message_sent_at' => now()->subHours(3)]);

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('does not retry a lead that already got a check-in (manual or automatic)', function () {
    $lead = metaLead();
    failWelcome($lead, at: now()->subHours(3));
    $lead->forceFill(['last_checkin_sent_at' => now()->subMinutes(5)])->save();

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('does not retry a Converted or Lost lead', function () {
    $converted = metaLead(['status' => LeadStatus::Converted]);
    failWelcome($converted, at: now()->subHours(3));

    $lost = metaLead(['status' => LeadStatus::Lost]);
    failWelcome($lost, at: now()->subHours(3));

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('skips a GMB-tagged lead -- it gets the Visibility Audit recovery nudge instead', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = metaLead(['service_id' => $gmb->id]);
    failWelcome($lead, at: now()->subHours(3));

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('gives up after the max attempts and leaves a note instead of retrying again', function () {
    $lead = metaLead();
    failWelcome($lead, times: Lead::WELCOME_RETRY_MAX_ATTEMPTS, at: now()->subHours(3));

    Artisan::call('app:retry-failed-lead-welcome-messages');

    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
    expect($lead->notes()->latest()->first()->body)->toContain('retry limit reached');
});

it('only posts the give-up note once, not on every subsequent run', function () {
    $lead = metaLead();
    failWelcome($lead, times: Lead::WELCOME_RETRY_MAX_ATTEMPTS, at: now()->subHours(3));

    Artisan::call('app:retry-failed-lead-welcome-messages');
    Artisan::call('app:retry-failed-lead-welcome-messages');

    expect($lead->notes()->where('body', 'like', '%retry limit reached%')->count())->toBe(1);
});

it('does not misclassify its own give-up note as an inbound reply', function () {
    $lead = metaLead();
    failWelcome($lead, times: Lead::WELCOME_RETRY_MAX_ATTEMPTS, at: now()->subHours(3));

    Artisan::call('app:retry-failed-lead-welcome-messages');

    expect($lead->fresh()->outreachAttemptSummary()['whatsapp_inbound_reply'])->toBeFalse();
});
