<?php

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Livewire\ManagerCalendar;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ManagerCalendarMetrics;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->metrics = app(ManagerCalendarMetrics::class);
});

it('lets admin and manager reach the calendar, but forbids other roles', function () {
    $this->seed(MenuItemsSeeder::class);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->get(route('manager-calendar.index'))->assertOk();
    $this->actingAs($manager)->get(route('manager-calendar.index'))->assertOk();
    $this->actingAs($sales)->get(route('manager-calendar.index'))->assertForbidden();
});

it('includes a meeting on the right date with the client as its subject and link', function () {
    $customer = Customer::factory()->create(['company_name' => 'Shridha Biotech']);
    Meeting::factory()->create([
        'meetable_type' => Customer::class, 'meetable_id' => $customer->id,
        'title' => 'Kickoff call', 'occurred_at' => now()->addDays(3)->setTime(14, 30),
    ]);

    $events = $this->metrics->eventsBetween(now()->startOfMonth(), now()->endOfMonth());
    $event = $events->firstWhere('type', 'meeting');

    expect($event)->not->toBeNull()
        ->and($event['title'])->toBe('Kickoff call')
        ->and($event['subtitle'])->toBe('Shridha Biotech')
        ->and($event['url'])->toBe(route('clients.show', $customer))
        ->and($event['date'])->toBe(now()->addDays(3)->toDateString());
});

it('links a lead meeting to the leads show page, not clients', function () {
    $lead = Lead::factory()->create(['name' => 'Prospective Co']);
    Meeting::factory()->create([
        'meetable_type' => Lead::class, 'meetable_id' => $lead->id,
        'occurred_at' => now()->addDay(),
    ]);

    $event = $this->metrics->eventsBetween(now()->startOfMonth(), now()->endOfMonth())->firstWhere('type', 'meeting');

    expect($event['subtitle'])->toBe('Prospective Co')
        ->and($event['url'])->toBe(route('leads.show', $lead));
});

it('excludes a Done task from the calendar, but includes an open one on its due date', function () {
    $open = Task::factory()->create(['status' => TaskStatus::Todo->value, 'due_date' => now()->addDays(2)]);
    Task::factory()->create(['status' => TaskStatus::Done->value, 'due_date' => now()->addDays(2)]);

    $events = $this->metrics->eventsBetween(now()->startOfMonth(), now()->endOfMonth())->where('type', 'task');

    expect($events)->toHaveCount(1)
        ->and($events->first()['title'])->toBe($open->title)
        ->and($events->first()['url'])->toBe(route('tasks.show', $open));
});

it('excludes a Completed project from the calendar, but includes an Active one on its end date', function () {
    $active = Project::factory()->create(['status' => ProjectStatus::Active->value, 'end_date' => now()->addWeek()]);
    Project::factory()->create(['status' => ProjectStatus::Completed->value, 'end_date' => now()->addWeek()]);

    $events = $this->metrics->eventsBetween(now()->startOfMonth(), now()->addMonth()->endOfMonth())->where('type', 'project');

    expect($events)->toHaveCount(1)
        ->and($events->first()['title'])->toBe($active->name.' (deadline)');
});

it('expands an approved multi-day leave request into one event per business day, skipping Sunday', function () {
    $user = User::factory()->create(['name' => 'Kiran']);
    $monday = now()->addWeeks(2)->startOfWeek(Carbon\Carbon::MONDAY);
    LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'status' => LeaveRequestStatus::Approved->value,
        'type' => LeaveRequestType::FullDay->value,
        'start_date' => $monday->toDateString(),
        'end_date' => $monday->copy()->addDays(6)->toDateString(), // Mon through next Sun
    ]);

    $events = $this->metrics->eventsBetween($monday->copy()->subDay(), $monday->copy()->addDays(7))->where('type', 'leave');

    expect($events)->toHaveCount(6) // Mon-Sat, Sunday excluded
        ->and($events->pluck('date')->contains($monday->copy()->addDays(6)->toDateString()))->toBeFalse()
        ->and($events->first()['title'])->toBe('Kiran');
});

it('excludes a pending (not yet approved) leave request entirely', function () {
    LeaveRequest::factory()->create([
        'status' => LeaveRequestStatus::Pending->value,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
    ]);

    $events = $this->metrics->eventsBetween(now()->startOfMonth(), now()->addMonth()->endOfMonth())->where('type', 'leave');

    expect($events)->toHaveCount(0);
});

it('toggling a filter off hides that types events from the rendered calendar', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create(['company_name' => 'Visible Co']);
    Meeting::factory()->create([
        'meetable_type' => Customer::class, 'meetable_id' => $customer->id,
        'title' => 'Only Meeting Today', 'occurred_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(ManagerCalendar::class)
        ->assertSee('Only Meeting Today')
        ->call('toggleType', 'meeting')
        ->assertDontSee('Only Meeting Today')
        ->call('toggleType', 'meeting')
        ->assertSee('Only Meeting Today');
});

it('navigates between months and back to today', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $currentMonth = now()->format('Y-m');
    $nextMonth = now()->addMonthNoOverflow()->format('Y-m');

    Livewire::actingAs($admin)
        ->test(ManagerCalendar::class)
        ->assertSet('month', $currentMonth)
        ->call('nextMonth')
        ->assertSet('month', $nextMonth)
        ->call('goToToday')
        ->assertSet('month', $currentMonth);
});

it('flags todays cell as isToday in the rendered grid', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $component = Livewire::actingAs($admin)->test(ManagerCalendar::class);
    $days = collect($component->viewData('days'));

    $today = $days->firstWhere('date', now()->toDateString());
    expect($today['isToday'])->toBeTrue();
});
