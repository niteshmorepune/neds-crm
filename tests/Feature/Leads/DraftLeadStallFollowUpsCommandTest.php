<?php

use App\Enums\LeadStatus;
use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Jobs\DraftLeadStallFollowUp;
use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Anchor "today" to a non-Sunday so the command doesn't self-skip.
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:40:00')); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Backdates a tagged Lead's created_at AND its own LogsActivity "created"
 * row -- the latter isn't backdated automatically (see [[feedback-gotchas]]
 * / DraftDealStallFollowUpsCommandTest's own identical helper) and would
 * otherwise read as recent activity, excluding the lead from every query
 * below regardless of the created_at override.
 */
function backdatedStalledLead(int $ownerId, int $daysAgo, StallReason $reason = StallReason::Budget, LeadStatus $status = LeadStatus::Contacted): Lead
{
    $lead = Lead::factory()->create(['owner_id' => $ownerId, 'stall_reason' => $reason, 'status' => $status]);
    $lead->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    $lead->activities()->update(['created_at' => now()->subDays($daysAgo)]);

    return $lead;
}

it('dispatches for a stall-tagged open lead untouched for 7+ days', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = backdatedStalledLead($owner->id, 7);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertDispatched(DraftLeadStallFollowUp::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch for an untagged lead, even if quiet a long time', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $owner->id, 'stall_reason' => null]);
    $lead->forceFill(['created_at' => now()->subDays(30)])->save();
    $lead->activities()->update(['created_at' => now()->subDays(30)]);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
});

it('does not dispatch for a tagged lead untouched less than 7 days', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedStalledLead($owner->id, 3);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
});

it('does not dispatch for a tagged lead with a recent note', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = backdatedStalledLead($owner->id, 10);
    $lead->notes()->create(['user_id' => $owner->id, 'body' => 'Talked yesterday.']);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
});

it('does not dispatch for a tagged lead with a recent call', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = backdatedStalledLead($owner->id, 10);
    CallLog::factory()->create(['callable_type' => Lead::class, 'callable_id' => $lead->id, 'called_at' => now()->subDay()]);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
});

it('does not dispatch for a Converted or Lost lead', function (LeadStatus $status) {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedStalledLead($owner->id, 30, StallReason::Budget, $status);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
})->with([
    'converted' => LeadStatus::Converted,
    'lost' => LeadStatus::Lost,
]);

it('does not dispatch for a tagged lead with no owner', function () {
    Bus::fake();
    $lead = Lead::factory()->create(['owner_id' => null, 'stall_reason' => StallReason::Budget]);
    $lead->forceFill(['created_at' => now()->subDays(10)])->save();
    $lead->activities()->update(['created_at' => now()->subDays(10)]);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
});

it('skips a lead already drafted for the current stale period', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = backdatedStalledLead($owner->id, 10);
    Activity::create([
        'user_id' => null,
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'event' => DraftLeadStallFollowUp::ACTIVITY_EVENT,
        'changes' => null,
    ]);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
});

it('respects a custom --days option', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = backdatedStalledLead($owner->id, 4);

    $this->artisan('app:draft-lead-stall-followups', ['--days' => 3])->assertSuccessful();

    Bus::assertDispatched(DraftLeadStallFollowUp::class, fn ($job) => $job->leadId === $lead->id);
});

it('skips Sundays', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-05 10:40:00')); // Sunday
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedStalledLead($owner->id, 10);

    $this->artisan('app:draft-lead-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftLeadStallFollowUp::class);
});
