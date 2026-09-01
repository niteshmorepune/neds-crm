<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Jobs\DraftDealStallFollowUp;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Anchor "today" to a non-Sunday so the command doesn't self-skip.
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:35:00')); // Wednesday
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Backdates a Deal's created_at AND its own LogsActivity "created" row (the
 * latter isn't backdated automatically -- see feedback-gotchas -- and would
 * otherwise read as recent activity, excluding the deal from every query
 * below regardless of the created_at override).
 */
function backdatedDeal(int $ownerId, int $daysAgo, DealStage $stage = DealStage::Negotiation): Deal
{
    $deal = Deal::factory()->ownedBy($ownerId)->stage($stage)->create();
    $deal->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    $deal->activities()->update(['created_at' => now()->subDays($daysAgo)]);

    return $deal;
}

it('dispatches for an open deal untouched for 7+ days', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = backdatedDeal($owner->id, 7);

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertDispatched(DraftDealStallFollowUp::class, fn ($job) => $job->dealId === $deal->id);
});

it('does not dispatch for a deal untouched less than 7 days', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedDeal($owner->id, 3);

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftDealStallFollowUp::class);
});

it('does not dispatch for a deal with a recent note', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = backdatedDeal($owner->id, 10);
    $deal->notes()->create(['user_id' => $owner->id, 'body' => 'Talked yesterday.']);

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftDealStallFollowUp::class);
});

it('does not dispatch for a deal with a recent logged edit', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = backdatedDeal($owner->id, 10);
    $deal->update(['value' => $deal->value + 100000]); // triggers a real LogsActivity "updated" row

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftDealStallFollowUp::class);
});

it('does not dispatch for a Won or Lost deal', function (DealStage $stage) {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedDeal($owner->id, 30, $stage);

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftDealStallFollowUp::class);
})->with([
    'won' => DealStage::Won,
    'lost' => DealStage::Lost,
]);

it('does not dispatch for a deal with no owner', function () {
    Bus::fake();
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(['owner_id' => null]);
    $deal->forceFill(['created_at' => now()->subDays(10)])->save();
    $deal->activities()->update(['created_at' => now()->subDays(10)]);

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftDealStallFollowUp::class);
});

it('skips a deal already drafted for the current stale period', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = backdatedDeal($owner->id, 10);
    Activity::create([
        'user_id' => null,
        'subject_type' => Deal::class,
        'subject_id' => $deal->id,
        'event' => DraftDealStallFollowUp::ACTIVITY_EVENT,
        'changes' => null,
    ]);

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftDealStallFollowUp::class);
});

it('re-dispatches once a genuine new touch supersedes a previous draft and it goes stale again', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = backdatedDeal($owner->id, 20);

    // A stall draft was written 15 days ago...
    Activity::create([
        'user_id' => null,
        'subject_type' => Deal::class,
        'subject_id' => $deal->id,
        'event' => DraftDealStallFollowUp::ACTIVITY_EVENT,
        'changes' => null,
    ]);
    Activity::where('subject_type', Deal::class)->where('subject_id', $deal->id)
        ->where('event', DraftDealStallFollowUp::ACTIVITY_EVENT)
        ->update(['created_at' => now()->subDays(15)]);

    // ...then the rep actually replied 12 days ago...
    $note = $deal->notes()->create(['user_id' => $owner->id, 'body' => 'Finally heard back.']);
    $note->forceFill(['created_at' => now()->subDays(12)])->save();

    // ...and it's gone quiet again since (12 days, past the 7-day threshold).
    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertDispatched(DraftDealStallFollowUp::class, fn ($job) => $job->dealId === $deal->id);
});

it('respects a custom --days option', function () {
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = backdatedDeal($owner->id, 4);

    $this->artisan('app:draft-deal-stall-followups', ['--days' => 3])->assertSuccessful();

    Bus::assertDispatched(DraftDealStallFollowUp::class, fn ($job) => $job->dealId === $deal->id);
});

it('skips Sundays', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-05 10:35:00')); // Sunday
    Bus::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedDeal($owner->id, 10);

    $this->artisan('app:draft-deal-stall-followups')->assertSuccessful();

    Bus::assertNotDispatched(DraftDealStallFollowUp::class);
});
