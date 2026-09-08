<?php

use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Jobs\DraftLeadStallFollowUp;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadStallFollowUpDrafted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function aiOnForLeadStall(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

function fakeLeadStallText(string $text): void
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
    $lead = Lead::factory()->create(['owner_id' => $owner->id]);
    aiOnForLeadStall();
    fakeLeadStallText('Hi, just checking in — happy to answer any questions.');
    Notification::fake();

    DraftLeadStallFollowUp::dispatchSync($lead->id);

    $note = $lead->notes()->latest()->first();
    expect($note)->not->toBeNull()
        ->and($note->user_id)->toBeNull()
        ->and($note->body)->toContain('gone quiet')
        ->and($note->body)->toContain('just checking in');

    expect(Activity::where('subject_type', Lead::class)
        ->where('subject_id', $lead->id)
        ->where('event', 'lead_stall_followup_drafted')
        ->exists())->toBeTrue();

    Notification::assertSentTo($owner, LeadStallFollowUpDrafted::class);
});

it('is idempotent -- does not draft a second note while the same stale period stands', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $owner->id]);
    aiOnForLeadStall();
    fakeLeadStallText('Checking in again.');
    Activity::create([
        'user_id' => null,
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'event' => DraftLeadStallFollowUp::ACTIVITY_EVENT,
        'changes' => null,
    ]);

    DraftLeadStallFollowUp::dispatchSync($lead->id);

    expect($lead->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $owner->id]);

    DraftLeadStallFollowUp::dispatchSync($lead->id);

    expect($lead->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the lead has no owner', function () {
    $lead = Lead::factory()->create(['owner_id' => null]);
    aiOnForLeadStall();
    Http::fake();

    DraftLeadStallFollowUp::dispatchSync($lead->id);

    expect($lead->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the lead no longer exists', function () {
    aiOnForLeadStall();
    Http::fake();

    DraftLeadStallFollowUp::dispatchSync(999999);

    Http::assertNothingSent();
});

it('grounds the draft in the lead\'s own notes, and names the tagged objection when present', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $owner->id, 'name' => 'Rupesh Kadam', 'stall_reason' => StallReason::Budget]);
    $lead->notes()->create(['user_id' => $owner->id, 'body' => 'Sent pricing on the 3rd.']);
    aiOnForLeadStall();
    fakeLeadStallText('Just checking in, Rupesh — happy to discuss a staged plan.');

    DraftLeadStallFollowUp::dispatchSync($lead->id);

    Http::assertSent(function ($request) {
        $prompt = json_decode($request->body(), true)['messages'][0]['content'];

        return str_contains($prompt, 'Lead: Rupesh Kadam')
            && str_contains($prompt, 'Sent pricing on the 3rd.')
            && str_contains($prompt, 'Known reason this has stalled: Budget / financial constraint');
    });
});

it('leaves the lead untouched when the AI call fails', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $owner->id]);
    aiOnForLeadStall();
    Notification::fake();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

    DraftLeadStallFollowUp::dispatchSync($lead->id);

    expect($lead->notes()->count())->toBe(0);
    Notification::assertNothingSent();
});
