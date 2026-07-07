<?php

use App\Enums\UserRole;
use App\Jobs\DraftLeadNurtureFollowUp;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadNurtureDrafted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function aiOnForNurture(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

function fakeNurtureText(string $text): void
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
    $lead = Lead::factory()->ownedBy($owner->id)->create();
    aiOnForNurture();
    fakeNurtureText('Hi Priya, just checking in on your SEO enquiry — happy to answer any questions!');
    Notification::fake();

    DraftLeadNurtureFollowUp::dispatchSync($lead->id, 1);

    $note = $lead->notes()->latest()->first();
    expect($note)->not->toBeNull()
        ->and($note->user_id)->toBeNull()
        ->and($note->body)->toContain('touch 1/3')
        ->and($note->body)->toContain('just checking in on your SEO enquiry');

    expect(Activity::where('subject_type', Lead::class)
        ->where('subject_id', $lead->id)
        ->where('event', 'lead_nurture_drafted')
        ->exists())->toBeTrue();

    Notification::assertSentTo($owner, LeadNurtureDrafted::class);
});

it('is idempotent — does not draft a second note for the same lead and touch', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();
    aiOnForNurture();
    fakeNurtureText('Following up again.');
    Activity::create([
        'user_id' => null,
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'event' => DraftLeadNurtureFollowUp::ACTIVITY_EVENT,
        'changes' => ['touch' => 1],
    ]);

    DraftLeadNurtureFollowUp::dispatchSync($lead->id, 1);

    expect($lead->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    DraftLeadNurtureFollowUp::dispatchSync($lead->id, 1);

    expect($lead->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the lead has no owner', function () {
    $lead = Lead::factory()->create(['owner_id' => null]);
    aiOnForNurture();
    Http::fake();

    DraftLeadNurtureFollowUp::dispatchSync($lead->id, 1);

    expect($lead->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the lead no longer exists', function () {
    aiOnForNurture();
    Http::fake();

    DraftLeadNurtureFollowUp::dispatchSync(999999, 1);

    Http::assertNothingSent();
});

it('leaves the lead untouched when the AI call fails', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();
    aiOnForNurture();
    Notification::fake();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

    DraftLeadNurtureFollowUp::dispatchSync($lead->id, 2);

    expect($lead->notes()->count())->toBe(0);
    Notification::assertNothingSent();
});
