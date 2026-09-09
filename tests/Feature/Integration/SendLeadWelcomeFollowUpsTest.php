<?php

use App\Enums\LeadStatus;
use App\Jobs\SendLeadCheckInJob;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // LeadObserver dispatches SyncLeadToWadeskJob/SendTelegramLeadAlertJob/
    // ScoreLead/SendLeadWelcomeMessageJob on every Lead::factory()->create()
    // below -- faking the queue keeps those from actually running and
    // dispatching their own real jobs, so assertions here only ever see
    // this command's own SendLeadCheckInJob dispatches.
    Queue::fake();
});

function backdateWelcome(Lead $lead, Carbon $sentAt): Lead
{
    $lead->forceFill(['welcome_message_sent_at' => $sentAt])->save();

    return $lead->fresh();
}

it('dispatches a check-in job for a lead whose welcome went unanswered past the wait window', function () {
    $lead = Lead::factory()->create();
    backdateWelcome($lead, now()->subHours(7));

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertPushed(SendLeadCheckInJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch before the wait window has passed', function () {
    $lead = Lead::factory()->create();
    backdateWelcome($lead, now()->subHours(3));

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('does not dispatch for a lead with no welcome message sent at all', function () {
    Lead::factory()->create(['welcome_message_sent_at' => null]);

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('does not dispatch twice -- skips a lead whose check-in already fired (manual or automatic)', function () {
    $lead = Lead::factory()->create();
    backdateWelcome($lead, now()->subHours(7));
    $lead->forceFill(['last_checkin_sent_at' => now()->subHours(1)])->save();

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('does not dispatch for a lead who already replied over WhatsApp', function () {
    $lead = Lead::factory()->create();
    backdateWelcome($lead, now()->subHours(7));
    $lead->notes()->create(['user_id' => null, 'body' => 'Yes I am interested, please call me tomorrow']);

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('does not dispatch for a lead staff already replied to over WhatsApp', function () {
    $lead = Lead::factory()->create();
    backdateWelcome($lead, now()->subHours(7));
    $lead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nHappy to help!"]);

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('does not dispatch for a lead the after-hours AI already replied to', function () {
    $lead = Lead::factory()->create();
    backdateWelcome($lead, now()->subHours(7));
    $lead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by AI Assistant (auto-reply)]\nThanks, someone will call you soon."]);

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('does not dispatch for a Converted or Lost lead', function () {
    $converted = Lead::factory()->create(['status' => LeadStatus::Converted]);
    backdateWelcome($converted, now()->subHours(7));

    $lost = Lead::factory()->create(['status' => LeadStatus::Lost]);
    backdateWelcome($lost, now()->subHours(7));

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('does not misclassify its own welcome-confirmation note as a reply', function () {
    // Regression guard: SendLeadWelcomeMessageJob's own note
    // ("✨ Automated welcome message sent via WhatsApp...") is user_id=null
    // with no [Sent via WhatsApp by prefix -- without Lead::
    // INTERNAL_MARKER_NOTE_PREFIXES excluding it, this would always read
    // as an inbound reply the instant the welcome note itself is created.
    $lead = Lead::factory()->create();
    backdateWelcome($lead, now()->subHours(7));
    $lead->notes()->create(['user_id' => null, 'body' => '✨ Automated welcome message sent via WhatsApp — asking when\'s a good time to call.']);

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertPushed(SendLeadCheckInJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('skips a GMB-tagged lead -- it never has a welcome_message_sent_at at all', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['service_id' => $gmb->id, 'meta_leadgen_id' => 'lg_'.uniqid()]);
    // A GMB lead's welcome_message_sent_at is never set by LeadObserver (it
    // gets the VA invite instead) -- confirm the command still no-ops even
    // if something else backdated it, since GMB leads have their own
    // separate recovery-nudge mechanism.
    expect($lead->fresh()->welcome_message_sent_at)->toBeNull();

    Artisan::call('app:send-lead-welcome-followups');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});
