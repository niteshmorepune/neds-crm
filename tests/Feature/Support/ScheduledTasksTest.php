<?php

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    Notification::fake();
});

// ── Frequency logic ───────────────────────────────────────────────────────────

it('creates a weekly_monday task on a Monday', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'Performance Marketing']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-06-29']) // Monday
        ->assertSuccessful();

    expect(Task::where('project_id', $project->id)->where('title', 'Performance marketing campaign review')->exists())->toBeTrue();
});

it('does not create a weekly_monday task on a non-Monday', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'Performance Marketing']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-06-30']) // Tuesday
        ->assertSuccessful();

    expect(Task::where('project_id', $project->id)->where('title', 'Performance marketing campaign review')->exists())->toBeFalse();
});

it('creates twice_monthly tasks on the 1st and 15th', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'Website Design & Development']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful();
    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-15'])->assertSuccessful();

    expect(Task::where('project_id', $project->id)->where('title', 'Website backup')->count())->toBe(2);
});

it('creates quarterly tasks only on 1st of Jan, Apr, Jul, Oct', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'SEO']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful(); // quarter start
    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-08-01'])->assertSuccessful(); // not quarter start

    expect(Task::where('project_id', $project->id)->where('title', 'Client portal contacts audit')->count())->toBe(1);
});

// ── Assignee resolution ───────────────────────────────────────────────────────

it('assigns the task to the project lead (pivot role=lead) not the owner', function () {
    $owner = User::factory()->role(UserRole::Support)->create();
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'SEO']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Active]);
    $project->assignees()->attach($lead->id, ['role' => 'lead']);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-06'])->assertSuccessful(); // Monday

    $task = Task::where('project_id', $project->id)->where('title', 'Technical SEO review')->first();
    expect($task?->assignee_id)->toBe($lead->id);
});

it('falls back to the project owner when no lead assignee is set', function () {
    $owner = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'SEO']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-06'])->assertSuccessful(); // Monday

    $task = Task::where('project_id', $project->id)->where('title', 'Technical SEO review')->first();
    expect($task?->assignee_id)->toBe($owner->id);
});

// ── Bell notification ─────────────────────────────────────────────────────────

it('fires a TaskAssigned in-app notification for each created task', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'SEO']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful();

    Notification::assertSentTo($lead, TaskAssigned::class);
});

// ── Idempotency ───────────────────────────────────────────────────────────────

it('does not create duplicate tasks when the command runs twice on the same day', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'Website Design & Development']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful();
    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful();

    expect(Task::where('project_id', $project->id)->where('title', 'Website backup')->count())->toBe(1);
});

// ── Filtering ─────────────────────────────────────────────────────────────────

it('skips projects that are on-hold or completed', function () {
    $owner = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'SEO']);

    Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::OnHold]);
    Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Completed]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful();

    expect(Task::where('title', 'Keyword ranking report')->exists())->toBeFalse();
});

it('skips projects with no matching service template', function () {
    $owner = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'AI Automation']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $owner->id, 'status' => ProjectStatus::Active]);

    // Project creation itself queues a one-time onboarding task (a separate
    // feature) — capture that baseline before asserting the *scheduled*
    // command adds nothing new, since AI Automation's recurring templates
    // are all monthly_1/monthly_1/monthly_1/quarterly and none fire on the
    // 6th (a Monday, but not the 1st of the month).
    $baseline = Task::where('project_id', $project->id)->count();

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-06'])->assertSuccessful(); // Monday

    expect(Task::where('project_id', $project->id)->count())->toBe($baseline);
});

it('gives an AMC Service project the same shared upkeep templates as Website Design & Development', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'AMC Service']);
    $project = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful();

    expect(Task::where('project_id', $project->id)->where('title', 'Website backup')->exists())->toBeTrue()
        ->and(Task::where('project_id', $project->id)->where('title', 'Monthly AMC report')->exists())->toBeTrue()
        ->and(Task::where('project_id', $project->id)->where('title', 'AMC contract renewal review')->exists())->toBeTrue();
});

it('creates tasks for multiple projects independently', function () {
    $lead = User::factory()->role(UserRole::Support)->create();
    $service = Service::factory()->create(['name' => 'GMB']);

    $p1 = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);
    $p2 = Project::factory()->create(['service_id' => $service->id, 'owner_id' => $lead->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:dispatch-scheduled-tasks', ['--date' => '2026-07-01'])->assertSuccessful();

    expect(Task::where('title', 'GMB profile & engagement review')->count())->toBe(2);
});
