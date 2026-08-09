<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectHealthMetrics;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(ProjectHealthMetrics::class);
});

it('lets admin and manager reach the project health page, but forbids other roles', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->get(route('project-health.index'))->assertOk();
    $this->actingAs($manager)->get(route('project-health.index'))->assertOk();
    $this->actingAs($sales)->get(route('project-health.index'))->assertForbidden();
});

it('flags a project red once its end_date has passed', function () {
    $project = Project::factory()->create(['end_date' => now()->subDay()]);

    expect($this->metrics->statusFor($project))->toBe('red');
});

it('flags a project orange when ending within 7 days with completion under 80 percent', function () {
    $project = Project::factory()->create(['end_date' => now()->addDays(3)]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done->value, 'due_date' => now()->addDay()]);
    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->addDay()]);

    expect($project->completionPercentage())->toBe(25)
        ->and($this->metrics->statusFor($project))->toBe('orange');
});

it('flags a project orange from an overdue task alone, even with a far-off deadline and high completion', function () {
    $project = Project::factory()->create(['end_date' => now()->addMonths(2)]);
    Task::factory()->count(9)->create(['project_id' => $project->id, 'status' => TaskStatus::Done->value, 'due_date' => now()->addDay()]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->subDay()]);

    expect($this->metrics->statusFor($project))->toBe('orange');
});

it('flags a project yellow when completion is under 50 percent and over half its timeline has elapsed', function () {
    $project = Project::factory()->create(['start_date' => now()->subDays(60), 'end_date' => now()->addDays(20)]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done->value, 'due_date' => now()->addDay()]);
    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->addDay()]);

    expect($this->metrics->statusFor($project))->toBe('yellow');
});

it('treats a project with no tasks yet as 0 percent complete for the yellow check', function () {
    $project = Project::factory()->create(['start_date' => now()->subDays(60), 'end_date' => now()->addDays(20)]);

    expect($project->completionPercentage())->toBeNull()
        ->and($this->metrics->statusFor($project))->toBe('yellow');
});

it('flags a project green when on track', function () {
    $project = Project::factory()->create(['start_date' => now(), 'end_date' => now()->addMonths(3)]);

    expect($this->metrics->statusFor($project))->toBe('green');
});

it('never flags a project with no end_date as red or yellow, only orange (via overdue tasks) or green', function () {
    $onTrack = Project::factory()->create(['start_date' => now()->subDays(60), 'end_date' => null]);
    Task::factory()->create(['project_id' => $onTrack->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->addDay()]);

    expect($this->metrics->statusFor($onTrack))->toBe('green');

    $withOverdue = Project::factory()->create(['end_date' => null]);
    Task::factory()->create(['project_id' => $withOverdue->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->subDay()]);

    expect($this->metrics->statusFor($withOverdue))->toBe('orange');
});

it('excludes a completed project from the health list entirely', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $completed = Project::factory()->create(['status' => ProjectStatus::Completed->value, 'end_date' => now()->subMonth()]);
    $active = Project::factory()->create(['name' => 'Live Project']);

    $response = $this->actingAs($admin)->get(route('project-health.index'));

    $rows = $response->viewData('rows');
    expect($rows->contains(fn (array $r) => $r['project']->id === $completed->id))->toBeFalse()
        ->and($rows->contains(fn (array $r) => $r['project']->id === $active->id))->toBeTrue();
});

it('sorts red before orange before yellow before green', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $green = Project::factory()->create(['name' => 'Green Co', 'start_date' => now(), 'end_date' => now()->addMonths(3)]);
    $red = Project::factory()->create(['name' => 'Red Co', 'end_date' => now()->subDay()]);
    $orange = Project::factory()->create(['name' => 'Orange Co', 'end_date' => now()->addMonths(2)]);
    Task::factory()->create(['project_id' => $orange->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->subDay()]);

    $response = $this->actingAs($admin)->get(route('project-health.index'));

    $names = $response->viewData('rows')->map(fn (array $r) => $r['project']->name)->all();
    expect(array_search('Red Co', $names))->toBeLessThan(array_search('Orange Co', $names))
        ->and(array_search('Orange Co', $names))->toBeLessThan(array_search('Green Co', $names));
});
