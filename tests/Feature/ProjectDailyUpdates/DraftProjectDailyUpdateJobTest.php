<?php

use App\Enums\TaskStatus;
use App\Jobs\DraftProjectDailyUpdate;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectDailyUpdateDrafted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function aiOnForProjectUpdate(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

function fakeProjectUpdateText(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 20],
        ]),
    ]);
}

it('drafts a pending update note and notifies the owner when a task was completed today', function () {
    aiOnForProjectUpdate();
    fakeProjectUpdateText('We wrapped up the homepage redesign for you today — looking great!');
    Notification::fake();

    $owner = User::factory()->create();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Done])
        ->forceFill(['completed_at' => now()])->saveQuietly();

    DraftProjectDailyUpdate::dispatchSync($project->id, now()->toDateString());

    $note = $project->notes()->latest()->first();
    expect($note)->not->toBeNull();
    expect($note->user_id)->toBeNull();
    expect($note->ai_generated)->toBeTrue();
    expect($note->visible_to_client)->toBeFalse();
    expect($note->body)->toContain('homepage redesign');

    expect(Activity::where('subject_type', Project::class)
        ->where('subject_id', $project->id)
        ->where('event', 'project_daily_update_drafted')
        ->exists())->toBeTrue();

    Notification::assertSentTo($owner, ProjectDailyUpdateDrafted::class);
});

it('does not draft a note or call AI when nothing was completed today', function () {
    aiOnForProjectUpdate();
    Http::fake();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create();

    DraftProjectDailyUpdate::dispatchSync($project->id, now()->toDateString());

    expect($project->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('is idempotent — does not draft a second note for the same project and day', function () {
    aiOnForProjectUpdate();
    fakeProjectUpdateText('Nice progress today.');
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create();
    $date = now()->toDateString();
    Activity::create([
        'user_id' => null,
        'subject_type' => Project::class,
        'subject_id' => $project->id,
        'event' => DraftProjectDailyUpdate::ACTIVITY_EVENT,
        'changes' => ['date' => $date],
    ]);

    DraftProjectDailyUpdate::dispatchSync($project->id, $date);

    expect(Note::where('notable_id', $project->id)->where('notable_type', Project::class)->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create();
    Task::factory()->for($project)->create(['status' => TaskStatus::Done])
        ->forceFill(['completed_at' => now()])->saveQuietly();

    DraftProjectDailyUpdate::dispatchSync($project->id, now()->toDateString());

    expect($project->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('only counts tasks completed on the target date', function () {
    aiOnForProjectUpdate();
    fakeProjectUpdateText('Great progress.');
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create();

    // Completed yesterday, not today — should not count toward today's update.
    Task::factory()->for($project)->create(['status' => TaskStatus::Done])
        ->forceFill(['completed_at' => now()->subDay()])->saveQuietly();

    DraftProjectDailyUpdate::dispatchSync($project->id, now()->toDateString());

    expect($project->notes()->count())->toBe(0);
    Http::assertNothingSent();
});
