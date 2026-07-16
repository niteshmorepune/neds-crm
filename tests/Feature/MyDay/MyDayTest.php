<?php

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\TaskStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\MyDayService;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->user = User::factory()->role(UserRole::Sales)->create();
    $this->service = app(MyDayService::class);
});

it('includes an overdue task assigned to the user and excludes a done one', function () {
    Task::factory()->create(['assignee_id' => $this->user->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Todo]);
    Task::factory()->create(['assignee_id' => $this->user->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Done]);

    $items = $this->service->worklist($this->user);

    expect($items->where('type', 'task'))->toHaveCount(1);
});

it('includes an overdue lead follow-up owned by the user', function () {
    Lead::factory()->create([
        'owner_id' => $this->user->id,
        'next_follow_up_at' => now()->subDay(),
        'status' => LeadStatus::New,
    ]);

    expect($this->service->worklist($this->user)->where('type', 'lead'))->toHaveCount(1);
});

it('includes an overdue deal follow-up owned by the user but excludes a Won deal', function () {
    Deal::factory()->create(['owner_id' => $this->user->id, 'next_follow_up_at' => now()->subDay(), 'stage' => DealStage::Proposal]);
    Deal::factory()->create(['owner_id' => $this->user->id, 'next_follow_up_at' => now()->subDay(), 'stage' => DealStage::Won, 'won_at' => now()]);

    expect($this->service->worklist($this->user)->where('type', 'deal'))->toHaveCount(1);
});

it('includes a due call follow-up logged by the user', function () {
    $customer = Customer::factory()->create();
    CallLog::factory()->create([
        'user_id' => $this->user->id,
        'callable_type' => Customer::class,
        'callable_id' => $customer->id,
        'follow_up_at' => now(),
    ]);

    expect($this->service->worklist($this->user)->where('type', 'call'))->toHaveCount(1);
});

it('includes an SLA-breached ticket assigned to the user but excludes a resolved one', function () {
    Ticket::factory()->create([
        'assignee_id' => $this->user->id,
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
        'sla_due_at' => now()->subHour(),
    ]);
    Ticket::factory()->create([
        'assignee_id' => $this->user->id,
        'status' => TicketStatus::Resolved,
        'priority' => TicketPriority::High,
        'sla_due_at' => now()->subHour(),
    ]);

    expect($this->service->worklist($this->user)->where('type', 'ticket'))->toHaveCount(1);
});

it('excludes items belonging to another user', function () {
    $other = User::factory()->role(UserRole::Sales)->create();
    Task::factory()->create(['assignee_id' => $other->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Todo]);

    expect($this->service->worklist($this->user))->toBeEmpty();
});

it('sorts the worklist by due time ascending, most overdue first', function () {
    Task::factory()->create(['assignee_id' => $this->user->id, 'due_date' => now()->subDays(1), 'status' => TaskStatus::Todo, 'title' => 'Less overdue']);
    Task::factory()->create(['assignee_id' => $this->user->id, 'due_date' => now()->subDays(5), 'status' => TaskStatus::Todo, 'title' => 'More overdue']);

    $items = $this->service->worklist($this->user);

    expect($items->first()['title'])->toBe('More overdue');
});

it('renders the My Day page for the logged-in user showing only their own items', function () {
    Task::factory()->create(['assignee_id' => $this->user->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Todo, 'title' => 'Mine']);
    $other = User::factory()->role(UserRole::Sales)->create();
    Task::factory()->create(['assignee_id' => $other->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Todo, 'title' => 'Not mine']);

    $this->actingAs($this->user)->get(route('my-day.index'))
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Not mine');
});

it('shows an empty state when nothing is due', function () {
    $this->actingAs($this->user)->get(route('my-day.index'))
        ->assertOk()
        ->assertSee('Nothing due right now');
});
