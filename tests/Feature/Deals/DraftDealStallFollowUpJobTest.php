<?php

use App\Enums\UserRole;
use App\Jobs\DraftDealStallFollowUp;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\DealStallFollowUpDrafted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function aiOnForDealStall(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

function fakeDealStallText(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 15],
        ]),
    ]);
}

it('drafts a note, logs the activity and notifies the owner', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->ownedBy($owner->id)->create();
    aiOnForDealStall();
    fakeDealStallText('Hi, just checking in — happy to answer any questions on our end.');
    Notification::fake();

    DraftDealStallFollowUp::dispatchSync($deal->id);

    $note = $deal->notes()->latest()->first();
    expect($note)->not->toBeNull()
        ->and($note->user_id)->toBeNull()
        ->and($note->body)->toContain('gone quiet')
        ->and($note->body)->toContain('just checking in');

    expect(Activity::where('subject_type', Deal::class)
        ->where('subject_id', $deal->id)
        ->where('event', 'deal_stall_followup_drafted')
        ->exists())->toBeTrue();

    Notification::assertSentTo($owner, DealStallFollowUpDrafted::class);
});

it('is idempotent -- does not draft a second note while the same stale period stands', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->ownedBy($owner->id)->create();
    aiOnForDealStall();
    fakeDealStallText('Checking in again.');
    Activity::create([
        'user_id' => null,
        'subject_type' => Deal::class,
        'subject_id' => $deal->id,
        'event' => DraftDealStallFollowUp::ACTIVITY_EVENT,
        'changes' => null,
    ]);

    DraftDealStallFollowUp::dispatchSync($deal->id);

    expect($deal->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->ownedBy($owner->id)->create();

    DraftDealStallFollowUp::dispatchSync($deal->id);

    expect($deal->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the deal has no owner', function () {
    $deal = Deal::factory()->create(['owner_id' => null]);
    aiOnForDealStall();
    Http::fake();

    DraftDealStallFollowUp::dispatchSync($deal->id);

    expect($deal->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the deal no longer exists', function () {
    aiOnForDealStall();
    Http::fake();

    DraftDealStallFollowUp::dispatchSync(999999);

    Http::assertNothingSent();
});

it('grounds the draft in the deal\'s own notes, with no call-history section', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->ownedBy($owner->id)->create(['title' => 'GMB retainer']);
    $deal->notes()->create(['user_id' => $owner->id, 'body' => 'Sent pricing on the 3rd.']);
    aiOnForDealStall();
    fakeDealStallText('Just checking in on the GMB retainer.');

    DraftDealStallFollowUp::dispatchSync($deal->id);

    Http::assertSent(function ($request) {
        $prompt = json_decode($request->body(), true)['messages'][0]['content'];

        return str_contains($prompt, 'Deal: GMB retainer')
            && str_contains($prompt, 'Sent pricing on the 3rd.')
            && ! str_contains($prompt, 'Call (');
    });
});

it('leaves the deal untouched when the AI call fails', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->ownedBy($owner->id)->create();
    aiOnForDealStall();
    Notification::fake();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

    DraftDealStallFollowUp::dispatchSync($deal->id);

    expect($deal->notes()->count())->toBe(0);
    Notification::assertNothingSent();
});
