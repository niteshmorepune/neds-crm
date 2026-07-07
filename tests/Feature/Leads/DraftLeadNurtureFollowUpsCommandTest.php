<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\DraftLeadNurtureFollowUp;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Anchor "today" to a non-Sunday so the command doesn't self-skip.
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:30:00')); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

it('dispatches touch 1 for an untouched lead enquired 1+ days ago', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDay()]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertDispatched(DraftLeadNurtureFollowUp::class, fn ($job) => $job->leadId === $lead->id && $job->touch === 1);
});

it('dispatches touch 2 for an untouched lead enquired 3+ days ago', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDays(3)]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertDispatched(DraftLeadNurtureFollowUp::class, fn ($job) => $job->leadId === $lead->id && $job->touch === 2);
});

it('dispatches touch 3 for an untouched lead enquired 7+ days ago', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDays(7)]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertDispatched(DraftLeadNurtureFollowUp::class, fn ($job) => $job->leadId === $lead->id && $job->touch === 3);
});

it('does not dispatch for a lead less than a day old', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subHours(2)]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadNurtureFollowUp::class);
});

it('skips a lead with a staff-authored note', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDay()]);
    $lead->notes()->create(['user_id' => $owner->id, 'body' => 'Called them already.']);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadNurtureFollowUp::class);
});

it('does not treat the system-authored enquiry note as a staff touch', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDay()]);
    // Mirrors what LeadCaptureController / WhatsappWebhookController write for the original enquiry.
    $lead->notes()->create(['user_id' => null, 'body' => 'Interested in SEO services.']);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertDispatched(DraftLeadNurtureFollowUp::class, fn ($job) => $job->leadId === $lead->id && $job->touch === 1);
});

it('skips a lead with a logged call', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDay()]);
    $lead->callLogs()->create([
        'user_id' => $owner->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now(),
    ]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadNurtureFollowUp::class);
});

it('skips a lead with no owner', function () {
    Bus::fake();
    Lead::factory()->create(['owner_id' => null, 'created_at' => now()->subDay()]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadNurtureFollowUp::class);
});

it('skips a lead that is not in New status', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($owner->id)->create([
        'created_at' => now()->subDay(),
        'status' => LeadStatus::Contacted,
    ]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadNurtureFollowUp::class);
});

it('skips a touch already drafted', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDay()]);
    Activity::create([
        'user_id' => null,
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'event' => DraftLeadNurtureFollowUp::ACTIVITY_EVENT,
        'changes' => ['touch' => 1],
    ]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadNurtureFollowUp::class);
});

it('skips Sundays', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-05 10:30:00')); // Sunday
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($owner->id)->create(['created_at' => now()->subDays(7)]);

    $this->artisan('app:draft-lead-nurture-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadNurtureFollowUp::class);
});
