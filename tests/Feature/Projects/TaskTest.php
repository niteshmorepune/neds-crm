<?php

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Livewire\TaskComments;
use App\Models\Deal;
use App\Models\Project;
use App\Models\Quotation;
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

it('links a task to a milestone belonging to its own project\'s deal', function () {
    $deal = Deal::factory()->create();
    $project = Project::factory()->create(['deal_id' => $deal->id]);
    $quotation = Quotation::factory()->create(['deal_id' => $deal->id]);
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000]);

    $this->actingAs($this->manager)->post(route('tasks.store'), [
        'title' => 'Deliver advance work', 'priority' => 'normal', 'status' => 'todo',
        'project_id' => $project->id, 'milestone_id' => $milestone->id,
    ])->assertRedirect();

    $task = Task::firstWhere('title', 'Deliver advance work');
    expect($task->milestone_id)->toBe($milestone->id);
});

it('rejects linking a task to a milestone from a different deal', function () {
    $deal = Deal::factory()->create();
    $project = Project::factory()->create(['deal_id' => $deal->id]);
    $otherQuotation = Quotation::factory()->create(['deal_id' => Deal::factory()->create()->id]);
    $unrelatedMilestone = $otherQuotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000]);

    $this->actingAs($this->manager)->post(route('tasks.store'), [
        'title' => 'Mismatched link', 'priority' => 'normal', 'status' => 'todo',
        'project_id' => $project->id, 'milestone_id' => $unrelatedMilestone->id,
    ])->assertSessionHasErrors('milestone_id');

    expect(Task::where('title', 'Mismatched link')->exists())->toBeFalse();
});

it('rejects a milestone link with no project selected', function () {
    $quotation = Quotation::factory()->create(['deal_id' => Deal::factory()->create()->id]);
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000]);

    $this->actingAs($this->manager)->post(route('tasks.store'), [
        'title' => 'Standalone with milestone', 'priority' => 'normal', 'status' => 'todo',
        'milestone_id' => $milestone->id,
    ])->assertSessionHasErrors('milestone_id');
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

it('filters tasks to pending (not-done) only via the pending flag', function () {
    Task::factory()->create(['title' => 'Todo one', 'status' => TaskStatus::Todo, 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Done one', 'status' => TaskStatus::Done, 'created_by' => $this->manager->id]);

    $this->actingAs($this->manager)->get(route('tasks.index', ['type' => 'all', 'pending' => 1]))
        ->assertOk()->assertSee('Todo one')->assertDontSee('Done one');
});

it('filters tasks to overdue only via the overdue flag', function () {
    Task::factory()->create(['title' => 'Overdue one', 'status' => TaskStatus::Todo, 'due_date' => now()->subDay(), 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Future one', 'status' => TaskStatus::Todo, 'due_date' => now()->addDay(), 'created_by' => $this->manager->id]);
    Task::factory()->create(['title' => 'Done but overdue', 'status' => TaskStatus::Done, 'due_date' => now()->subDay(), 'created_by' => $this->manager->id]);

    $this->actingAs($this->manager)->get(route('tasks.index', ['type' => 'all', 'overdue' => 1]))
        ->assertOk()->assertSee('Overdue one')->assertDontSee('Future one')->assertDontSee('Done but overdue');
});

it('filters tasks to completed today via the completed_today flag', function () {
    $completedToday = Task::factory()->create(['title' => 'Finished today', 'status' => TaskStatus::Done, 'created_by' => $this->manager->id]);
    $completedYesterday = Task::factory()->create(['title' => 'Finished yesterday', 'status' => TaskStatus::Done, 'created_by' => $this->manager->id]);
    $completedYesterday->updated_at = now()->subDay();
    $completedYesterday->saveQuietly();

    $this->actingAs($this->manager)->get(route('tasks.index', ['type' => 'all', 'completed_today' => 1]))
        ->assertOk()->assertSee('Finished today')->assertDontSee('Finished yesterday');
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

it('lets a Project Manager (project owner_id) see a task an assignee created on their project, even without being a project team member themselves', function () {
    $projectManager = User::factory()->role(UserRole::Sales)->create();
    $assignee = User::factory()->role(UserRole::Support)->create();
    $project = Project::factory()->create(['owner_id' => $projectManager->id]);
    // Deliberately NOT added to $project->assignees() — owner_id alone should be enough.
    $task = Task::factory()->create([
        'title' => 'Assignee-created task',
        'project_id' => $project->id,
        'assignee_id' => $assignee->id,
        'created_by' => $assignee->id,
    ]);

    $this->actingAs($projectManager)->get(route('tasks.index', ['type' => 'all']))
        ->assertOk()->assertSee('Assignee-created task');

    $this->actingAs($projectManager)->get(route('tasks.show', $task))->assertOk();
});

it('does not let a non-owning, non-assigned, non-team-member user see a task on someone else\'s project', function () {
    $outsider = User::factory()->role(UserRole::Sales)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Not yours']);

    $this->actingAs($outsider)->get(route('tasks.index', ['type' => 'all']))
        ->assertOk()->assertDontSee('Not yours');

    $this->actingAs($outsider)->get(route('tasks.show', $task))->assertForbidden();
});

it('shows "Standalone" for a task that never had a project', function () {
    $task = Task::factory()->assignedTo($this->manager->id)->create(['project_id' => null]);

    expect($task->projectLabel())->toBe('Standalone');
});

it('shows "Project removed" (not "Standalone") for a task whose project was soft-deleted', function () {
    // Regression: a real bug report ("a standalone SEO project is being
    // auto-created") turned out to be this exact task-display confusion —
    // Project uses SoftDeletes, so deleting one doesn't fire the DB's
    // ON DELETE CASCADE (that only fires on a real DELETE, not a soft
    // one). The task survives with its real project_id intact, but
    // $task->project resolves to null (Eloquent excludes trashed rows by
    // default) — indistinguishable from a genuinely standalone task
    // without this fix.
    $project = Project::factory()->create(['name' => 'ADTA Group SEO']);
    $task = Task::factory()->assignedTo($this->manager->id)->create(['project_id' => $project->id]);
    $project->delete();

    expect($task->fresh()->projectLabel())->toBe('Project removed')
        ->and($task->fresh()->project_id)->toBe($project->id);

    $this->actingAs($this->manager)->get(route('tasks.show', $task))
        ->assertOk()->assertSee('Project removed')->assertDontSee('ADTA Group SEO');
});

it('shows the real project name for a task on a live project', function () {
    $project = Project::factory()->create(['name' => 'Live Project']);
    $task = Task::factory()->assignedTo($this->manager->id)->create(['project_id' => $project->id]);

    expect($task->projectLabel())->toBe('Live Project');
});

it('renders task index, create and show pages', function () {
    $task = Task::factory()->assignedTo($this->manager->id)->create();

    $this->actingAs($this->manager)->get(route('tasks.index'))->assertOk()->assertSee('Emptask');
    $this->actingAs($this->manager)->get(route('tasks.create'))->assertOk()->assertSee('Title');
    $this->actingAs($this->manager)->get(route('tasks.show', $task))->assertOk()->assertSee($task->title);
});
