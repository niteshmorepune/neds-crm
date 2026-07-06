<?php

use App\Enums\ProjectStatus;
use App\Jobs\CreateOnboardingTasks;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

it('dispatches CreateOnboardingTasks when an active project is created', function () {
    Bus::fake();
    $service = Service::factory()->create(['name' => 'SEO']);

    $project = Project::factory()->create(['service_id' => $service->id, 'status' => ProjectStatus::Active]);

    Bus::assertDispatched(CreateOnboardingTasks::class, fn ($job) => $job->projectId === $project->id);
});

it('does not dispatch onboarding tasks for a non-active project', function () {
    Bus::fake();
    Project::factory()->create(['status' => ProjectStatus::OnHold]);

    Bus::assertNotDispatched(CreateOnboardingTasks::class);
});

it('creates the matching onboarding checklist for the project service, assigned to the lead', function () {
    Notification::fake();
    // Bus::fake() suppresses the automatic dispatch-on-create for this setup
    // step, so we can attach the lead assignee before running the job
    // ourselves — mirroring real production timing (the queued job runs
    // later, after the request's own assignees()->sync() has committed),
    // not the test suite's QUEUE_CONNECTION=sync, which would otherwise run
    // the job synchronously inside Project::create(), before the lead is
    // attached below.
    Bus::fake();
    $owner = User::factory()->create();
    $lead = User::factory()->create();
    $service = Service::factory()->create(['name' => 'SEO']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Active]);
    $project->assignees()->attach($lead->id, ['role' => 'lead']);

    (new CreateOnboardingTasks($project->id))->handle();

    $titles = ['Technical SEO setup', 'On-page SEO setup', 'Off-page SEO setup', 'Initial SEO report'];
    foreach ($titles as $title) {
        $task = Task::where('project_id', $project->id)->where('title', $title)->first();
        expect($task)->not->toBeNull();
        expect($task->assignee_id)->toBe($lead->id);
    }
    Notification::assertSentTo($lead, TaskAssigned::class);
});

it('falls back to the project owner when no lead assignee is set', function () {
    $owner = User::factory()->create();
    $service = Service::factory()->create(['name' => 'GMB']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Active]);

    (new CreateOnboardingTasks($project->id))->handle();

    $task = Task::where('project_id', $project->id)->where('title', 'GMB profile setup')->first();
    expect($task?->assignee_id)->toBe($owner->id);
});

it('does not create duplicate onboarding tasks when the job runs twice', function () {
    $owner = User::factory()->create();
    $service = Service::factory()->create(['name' => 'GMB']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Active]);

    (new CreateOnboardingTasks($project->id))->handle();
    (new CreateOnboardingTasks($project->id))->handle();

    expect(Task::where('project_id', $project->id)->where('title', 'GMB profile setup')->count())->toBe(1);
});

it('does nothing when the project has no owner or assignee at all', function () {
    $service = Service::factory()->create(['name' => 'GMB']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => null, 'status' => ProjectStatus::Active]);

    (new CreateOnboardingTasks($project->id))->handle();

    expect(Task::where('project_id', $project->id)->exists())->toBeFalse();
});

it('creates the AMC Service onboarding audit for a new AMC Service project', function () {
    $owner = User::factory()->create();
    $service = Service::factory()->create(['name' => 'AMC Service']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Active]);

    (new CreateOnboardingTasks($project->id))->handle();

    expect(Task::where('project_id', $project->id)->where('title', 'AMC onboarding audit')->exists())->toBeTrue();
});
