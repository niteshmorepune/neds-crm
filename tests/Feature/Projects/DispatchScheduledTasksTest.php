<?php

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

function activeProjectForService(string $serviceName): Project
{
    $owner = User::factory()->role(UserRole::Manager)->create();
    $service = Service::factory()->create(['name' => $serviceName]);

    return Project::factory()->create([
        'service_id' => $service->id,
        'owner_id' => $owner->id,
        'status' => ProjectStatus::Active,
    ]);
}

it('creates the weekly GMB post task on a Monday for a project tagged GMB', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-07')); // a Monday
    $project = activeProjectForService('GMB');

    $this->artisan('app:dispatch-scheduled-tasks')->assertExitCode(0);

    expect(Task::where('project_id', $project->id)->where('title', 'Weekly Google post')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('does not create the weekly GMB post task on a non-Monday', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-08')); // a Tuesday
    $project = activeProjectForService('GMB');

    $this->artisan('app:dispatch-scheduled-tasks')->assertExitCode(0);

    expect(Task::where('project_id', $project->id)->where('title', 'Weekly Google post')->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('never creates the same scheduled task twice for the same project on the same day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-07'));
    $project = activeProjectForService('GMB');

    $this->artisan('app:dispatch-scheduled-tasks');
    $this->artisan('app:dispatch-scheduled-tasks');

    expect(Task::where('project_id', $project->id)->where('title', 'Weekly Google post')->count())->toBe(1);

    Carbon::setTestNow();
});

// ──────────────────────────────────────────────────────────────────────────────
// Real incident, 2026-09-04: 'GMB'/'Social Media'/'AMC Service' were each
// renamed live in production, silently breaking the exact-name match — see
// ServiceTaskMatcher's own docblock.
// ──────────────────────────────────────────────────────────────────────────────

it('still creates the weekly GMB post when the service is named "GMB Services"', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-07'));
    $project = activeProjectForService('GMB Services');

    $this->artisan('app:dispatch-scheduled-tasks');

    expect(Task::where('project_id', $project->id)->where('title', 'Weekly Google post')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('still creates the monthly social media report when the service is named "Social Media Management"', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-01')); // monthly_1
    $project = activeProjectForService('Social Media Management');

    $this->artisan('app:dispatch-scheduled-tasks');

    expect(Task::where('project_id', $project->id)->where('title', 'Monthly social media report')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('still creates the monthly AMC report when the service is named "Annual Maintenance Services"', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-01'));
    $project = activeProjectForService('Annual Maintenance Services');

    $this->artisan('app:dispatch-scheduled-tasks');

    expect(Task::where('project_id', $project->id)->where('title', 'Monthly AMC report')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('still creates the AMC-shared website backup task for a renamed AMC project, on its twice-monthly date', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-01'));
    $project = activeProjectForService('Annual Maintenance Services');

    $this->artisan('app:dispatch-scheduled-tasks');

    expect(Task::where('project_id', $project->id)->where('title', 'Website backup')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('creates the all-services quarterly portal-contacts-audit task for a renamed GMB project', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01')); // quarterly
    $project = activeProjectForService('GMB Services');

    $this->artisan('app:dispatch-scheduled-tasks');

    expect(Task::where('project_id', $project->id)->where('title', 'Client portal contacts audit')->exists())->toBeTrue();

    Carbon::setTestNow();
});
