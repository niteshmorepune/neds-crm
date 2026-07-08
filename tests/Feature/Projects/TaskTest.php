<?php

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Livewire\TaskComments;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('creates a task and records the creator', function () {
    $this->actingAs($this->manager)->post(route('tasks.store'), [
        'title' => 'Write copy', 'priority' => 'normal', 'status' => 'todo',
    ])->assertRedirect();

    $task = Task::firstWhere('title', 'Write copy');
    expect($task->created_by)->toBe($this->manager->id);
});

it('updates task status via the quick action', function () {
    $task = Task::factory()->create(['assignee_id' => $this->manager->id]);

    $this->actingAs($this->manager)->patch(route('tasks.status', $task), ['status' => 'done'])->assertRedirect();

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('scopes the My Tasks filter to the current user', function () {
    $mine = Task::factory()->assignedTo($this->manager->id)->create(['title' => 'Mine', 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Someone elses', 'created_by' => $this->manager->id]);

    $this->actingAs($this->manager)->get(route('tasks.index', ['mine' => 1]))
        ->assertOk()->assertSee('Mine')->assertDontSee('Someone elses');
});

it('hides other users tasks from a non-manager list', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Task::factory()->assignedTo($sales->id)->create(['title' => 'Sales task', 'created_by' => $sales->id]);
    Task::factory()->create(['title' => 'Hidden task', 'assignee_id' => User::factory()->create()->id, 'created_by' => $this->manager->id]);

    $this->actingAs($sales)->get(route('tasks.index'))->assertOk()
        ->assertSee('Sales task')->assertDontSee('Hidden task');
});

it('defaults the task list to assigned tasks, hiding routine maintenance ones', function () {
    Task::factory()->create(['title' => 'Fix contact form', 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Google Search Console review', 'created_by' => null]);

    $this->actingAs($this->manager)->get(route('tasks.index'))
        ->assertOk()->assertSee('Fix contact form')->assertDontSee('Google Search Console review');
});

it('shows only routine maintenance tasks when that filter is selected', function () {
    Task::factory()->create(['title' => 'Fix contact form', 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Google Search Console review', 'created_by' => null]);

    $this->actingAs($this->manager)->get(route('tasks.index', ['type' => 'routine']))
        ->assertOk()->assertSee('Google Search Console review')->assertDontSee('Fix contact form');
});

it('shows both task types when "all tasks" is selected', function () {
    Task::factory()->create(['title' => 'Fix contact form', 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Google Search Console review', 'created_by' => null]);

    $this->actingAs($this->manager)->get(route('tasks.index', ['type' => 'all']))
        ->assertOk()->assertSee('Fix contact form')->assertSee('Google Search Console review');
});

it('shows a team workload summary with combined assigned + routine counts, broken down by status and overdue', function () {
    $mohit = User::factory()->create(['name' => 'Mohit Patil']);
    // 2 To Do (1 overdue), 1 In Progress (overdue), 1 Review, 1 Done (routine,
    // not overdue even though its due date is past — Done never counts as overdue).
    Task::factory()->create(['assignee_id' => $mohit->id, 'created_by' => $this->manager->id, 'status' => TaskStatus::Todo, 'due_date' => now()->subDay()]);
    Task::factory()->create(['assignee_id' => $mohit->id, 'created_by' => $this->manager->id, 'status' => TaskStatus::Todo, 'due_date' => now()->addWeek()]);
    Task::factory()->create(['assignee_id' => $mohit->id, 'created_by' => null, 'status' => TaskStatus::InProgress, 'due_date' => now()->subDay()]);
    Task::factory()->create(['assignee_id' => $mohit->id, 'created_by' => $this->manager->id, 'status' => TaskStatus::Review, 'due_date' => now()->addWeek()]);
    Task::factory()->create(['assignee_id' => $mohit->id, 'created_by' => null, 'status' => TaskStatus::Done, 'due_date' => now()->subDay()]);

    $response = $this->actingAs($this->manager)->get(route('tasks.index'));

    $response->assertOk()->assertSee('Team workload')
        // Total=5, To Do=2, In Progress=1, Review=1, Done=1, Overdue=2 — in column order.
        ->assertSeeInOrder(['Mohit Patil', '5', '2', '1', '1', '1', '2']);
});

it('hides the team workload summary from non-managers', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Task::factory()->create(['assignee_id' => $sales->id, 'created_by' => $sales->id]);

    $this->actingAs($sales)->get(route('tasks.index'))->assertOk()->assertDontSee('Team workload');
});

it('filters the task list to one team member via the assignee filter', function () {
    $mohit = User::factory()->create(['name' => 'Mohit Patil']);
    $manali = User::factory()->create(['name' => 'Manali Jasud']);
    Task::factory()->create(['title' => 'Mohit task', 'assignee_id' => $mohit->id, 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Manali task', 'assignee_id' => $manali->id, 'created_by' => $this->manager->id]);

    $this->actingAs($this->manager)->get(route('tasks.index', ['assignee' => $mohit->id, 'type' => 'all']))
        ->assertOk()
        ->assertSee('Mohit task')
        ->assertDontSee('Manali task')
        ->assertSee('Filtered to');
});

it('lets a participant comment but forbids outsiders', function () {
    $task = Task::factory()->assignedTo($this->manager->id)->create();

    Livewire::actingAs($this->manager)->test(TaskComments::class, ['task' => $task, 'canManage' => true])
        ->set('body', 'On it')->call('addComment')->assertHasNoErrors();
    expect($task->comments()->count())->toBe(1);

    $outsider = User::factory()->role(UserRole::Sales)->create();
    Livewire::actingAs($outsider)->test(TaskComments::class, ['task' => $task, 'canManage' => false])
        ->set('body', 'sneaky')->call('addComment')->assertForbidden();
});

it('uploads an attachment and streams it back, blocking outsiders', function () {
    Storage::fake('local');
    $task = Task::factory()->assignedTo($this->manager->id)->create();

    $this->actingAs($this->manager)
        ->post(route('tasks.attachments.store', $task), ['file' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')])
        ->assertRedirect();

    $attachment = $task->attachments()->first();
    expect($attachment)->not->toBeNull();
    Storage::disk('local')->assertExists($attachment->path);

    // Owner/manager can download.
    $this->actingAs($this->manager)->get(route('attachments.download', $attachment))->assertOk();

    // An outsider cannot.
    $outsider = User::factory()->role(UserRole::Sales)->create();
    $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertForbidden();
});

it('blocks support from assigning a task to another employee', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $colleague = User::factory()->create();

    $this->actingAs($support)->post(route('tasks.store'), [
        'title' => 'Follow up with client', 'priority' => 'normal', 'status' => 'todo',
        'assignee_id' => $colleague->id,
    ])->assertSessionHasErrors('assignee_id');

    expect(Task::firstWhere('title', 'Follow up with client'))->toBeNull();
});

it('lets support assign a task to themselves or leave it unassigned', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->post(route('tasks.store'), [
        'title' => 'Self task', 'priority' => 'normal', 'status' => 'todo',
        'assignee_id' => $support->id,
    ])->assertRedirect();

    expect(Task::firstWhere('title', 'Self task'))->not->toBeNull();
});

it('lets support update other fields on a task already assigned to someone else without re-triggering the assignment block', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $colleague = User::factory()->create();
    $task = Task::factory()->create(['assignee_id' => $colleague->id, 'created_by' => $support->id, 'status' => TaskStatus::Todo]);

    $this->actingAs($support)->put(route('tasks.update', $task), [
        'title' => $task->title, 'priority' => 'normal', 'status' => 'in_progress',
        'assignee_id' => $colleague->id,
    ])->assertRedirect();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('renders task index, create and show pages', function () {
    $task = Task::factory()->assignedTo($this->manager->id)->create();

    $this->actingAs($this->manager)->get(route('tasks.index'))->assertOk()->assertSee('Emptask');
    $this->actingAs($this->manager)->get(route('tasks.create'))->assertOk()->assertSee('Title');
    $this->actingAs($this->manager)->get(route('tasks.show', $task))->assertOk()->assertSee($task->title);
});
