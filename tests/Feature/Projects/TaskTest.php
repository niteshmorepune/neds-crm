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
    $mine = Task::factory()->assignedTo($this->manager->id)->create(['title' => 'Mine']);
    Task::factory()->create(['title' => 'Someone elses']);

    $this->actingAs($this->manager)->get(route('tasks.index', ['mine' => 1]))
        ->assertOk()->assertSee('Mine')->assertDontSee('Someone elses');
});

it('hides other users tasks from a non-manager list', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Task::factory()->assignedTo($sales->id)->create(['title' => 'Sales task']);
    Task::factory()->create(['title' => 'Hidden task', 'assignee_id' => User::factory()->create()->id]);

    $this->actingAs($sales)->get(route('tasks.index'))->assertOk()
        ->assertSee('Sales task')->assertDontSee('Hidden task');
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

it('renders task index, create and show pages', function () {
    $task = Task::factory()->assignedTo($this->manager->id)->create();

    $this->actingAs($this->manager)->get(route('tasks.index'))->assertOk()->assertSee('Emptask');
    $this->actingAs($this->manager)->get(route('tasks.create'))->assertOk()->assertSee('Title');
    $this->actingAs($this->manager)->get(route('tasks.show', $task))->assertOk()->assertSee($task->title);
});
