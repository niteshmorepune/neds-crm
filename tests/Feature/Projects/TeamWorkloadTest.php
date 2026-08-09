<?php

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

function openTasksFor(User $user, int $count, array $attributes = []): void
{
    Task::factory()->count($count)->create(array_merge([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Todo->value,
    ], $attributes));
}

it('lets admin and manager reach the team workload page, but forbids other roles', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($admin)->get(route('team-workload.index'))->assertOk();
    $this->actingAs($manager)->get(route('team-workload.index'))->assertOk();
    $this->actingAs($support)->get(route('team-workload.index'))->assertForbidden();
});

it('flags a person overloaded when their open task count exceeds 1.5x their roles average', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $support1 = User::factory()->role(UserRole::Support)->create();
    $support2 = User::factory()->role(UserRole::Support)->create();
    $support3 = User::factory()->role(UserRole::Support)->create();

    openTasksFor($support1, 2);
    openTasksFor($support2, 2);
    // Average of the other two is 2; 1.5x average = 3. 10 > 3, so overloaded.
    openTasksFor($support3, 10);

    $response = $this->actingAs($admin)->get(route('team-workload.index'));

    $rows = $response->viewData('rows')->keyBy(fn (array $r) => $r['user']->id);
    expect($rows[$support3->id]['overloaded'])->toBeTrue()
        ->and($rows[$support1->id]['overloaded'])->toBeFalse()
        ->and($rows[$support2->id]['overloaded'])->toBeFalse();
});

it('flags a person overloaded with 3+ overdue tasks even when their open count is at the role average', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $support1 = User::factory()->role(UserRole::Support)->create();
    $support2 = User::factory()->role(UserRole::Support)->create();

    openTasksFor($support1, 3);
    openTasksFor($support2, 3, ['due_date' => now()->subDay()->toDateString()]);

    $response = $this->actingAs($admin)->get(route('team-workload.index'));

    $rows = $response->viewData('rows')->keyBy(fn (array $r) => $r['user']->id);
    expect($rows[$support2->id]['overdue_tasks'])->toBe(3)
        ->and($rows[$support2->id]['overloaded'])->toBeTrue()
        ->and($rows[$support1->id]['overloaded'])->toBeFalse();
});

it('does not flag the only person in a role group as overloaded purely from the ratio check', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    openTasksFor($telecaller, 25);

    $response = $this->actingAs($admin)->get(route('team-workload.index'));

    $rows = $response->viewData('rows')->keyBy(fn (array $r) => $r['user']->id);
    expect($rows[$telecaller->id]['overloaded'])->toBeFalse()
        ->and($rows[$telecaller->id]['role_average_open_tasks'])->toBe(25.0);
});

it('excludes admin and manager from the workload rows entirely', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    openTasksFor($manager, 50);

    $response = $this->actingAs($admin)->get(route('team-workload.index'));

    $rows = $response->viewData('rows');
    expect($rows->contains(fn (array $r) => $r['user']->id === $manager->id))->toBeFalse()
        ->and($rows->contains(fn (array $r) => $r['user']->id === $admin->id))->toBeFalse();
});

it('excludes an inactive user from the workload rows', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $inactive = User::factory()->role(UserRole::Support)->create(['is_active' => false]);
    openTasksFor($inactive, 5);

    $response = $this->actingAs($admin)->get(route('team-workload.index'));

    expect($response->viewData('rows')->contains(fn (array $r) => $r['user']->id === $inactive->id))->toBeFalse();
});

it('includes a workload-role user with zero tasks, since an empty plate is itself a capacity signal', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $idleSales = User::factory()->role(UserRole::Sales)->create();

    $response = $this->actingAs($admin)->get(route('team-workload.index'));

    $rows = $response->viewData('rows')->keyBy(fn (array $r) => $r['user']->id);
    expect($rows[$idleSales->id]['open_tasks'])->toBe(0)
        ->and($rows[$idleSales->id]['overloaded'])->toBeFalse();
});

it('does not count a Done task toward open_tasks', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $support = User::factory()->role(UserRole::Support)->create();
    openTasksFor($support, 2);
    openTasksFor($support, 5, ['status' => TaskStatus::Done->value]);

    $response = $this->actingAs($admin)->get(route('team-workload.index'));

    $rows = $response->viewData('rows')->keyBy(fn (array $r) => $r['user']->id);
    expect($rows[$support->id]['open_tasks'])->toBe(2);
});
