<?php

use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DashboardMetrics;

beforeEach(function () {
    $this->metrics = app(DashboardMetrics::class);
});

it('counts clients by status for the admin cards', function () {
    Customer::factory()->count(3)->create(['status' => CustomerStatus::Active]);
    Customer::factory()->count(2)->create(['status' => CustomerStatus::Inactive]);

    $stats = $this->metrics->adminStats();

    expect($stats['clients_total']['value'])->toBe(5)
        ->and($stats['clients_active']['value'])->toBe(3)
        ->and($stats['clients_inactive']['value'])->toBe(2);
});

it('builds the services overview with percentages', function () {
    $seo = Service::factory()->create(['name' => 'SEO']);
    $web = Service::factory()->create(['name' => 'Website']);
    Project::factory()->count(3)->create(['service_id' => $seo->id]);
    Project::factory()->create(['service_id' => $web->id]);

    $overview = $this->metrics->servicesOverview();

    expect($overview['total'])->toBe(4)
        ->and($overview['segments'][0])->toMatchArray(['name' => 'SEO', 'count' => 3, 'percent' => 75.0])
        ->and($overview['segments'][1])->toMatchArray(['name' => 'Website', 'count' => 1, 'percent' => 25.0]);
});

it('summarizes tasks into assigned/pending/overdue/completed', function () {
    Task::factory()->create(['status' => TaskStatus::Done]);
    Task::factory()->create(['status' => TaskStatus::Todo, 'due_date' => now()->subDay()]); // overdue
    Task::factory()->create(['status' => TaskStatus::InProgress, 'due_date' => now()->addWeek()]);

    $summary = $this->metrics->taskSummary();

    expect($summary['assigned'])->toBe(3)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['pending'])->toBe(2)
        ->and($summary['overdue'])->toBe(1);
});

it('computes sales pipeline value by open stage and won this month', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Proposal, 'value' => 500000]);
    // Won this month — won_at auto-stamped to now() by saving hook.
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'value' => 1000000]);
    // Won last month — should be excluded from won_this_month_value.
    Deal::factory()->create(['owner_id' => $sales->id, 'stage' => DealStage::Won, 'value' => 999999, 'won_at' => now()->subMonth()]);

    $stats = $this->metrics->salesStats($sales);

    expect($stats['won_this_month_value'])->toBe(1000000)
        ->and(collect($stats['pipeline'])->firstWhere('stage', 'Proposal')['value'])->toBe(500000);
});

it('computes accounts outstanding, collections and overdue count', function () {
    $inv = Invoice::factory()->create(['status' => InvoiceStatus::PartiallyPaid, 'total' => 1000000, 'amount_paid' => 400000]);
    Invoice::factory()->create(['status' => InvoiceStatus::Overdue, 'total' => 200000, 'amount_paid' => 0]);
    Payment::factory()->create(['invoice_id' => $inv->id, 'amount' => 400000, 'paid_on' => now()]);

    $stats = $this->metrics->accountsStats();

    expect($stats['outstanding'])->toBe(800000) // 600000 + 200000
        ->and($stats['collected_this_month'])->toBe(400000)
        ->and($stats['overdue_count'])->toBe(1);
});

it('returns pending tasks, completed today and active project count for an intern', function () {
    $intern = User::factory()->role(UserRole::Intern)->create();

    Task::factory()->create(['assignee_id' => $intern->id, 'status' => TaskStatus::Todo]);
    Task::factory()->create(['assignee_id' => $intern->id, 'status' => TaskStatus::Done, 'updated_at' => now()]);
    Task::factory()->create(['status' => TaskStatus::Todo]); // another user's task

    $project = Project::factory()->create();
    $project->assignees()->attach($intern->id, ['role' => 'member']);
    Project::factory()->create(['status' => 'completed']); // excluded

    $stats = app(DashboardMetrics::class)->internStats($intern);

    expect($stats['pendingTasks'])->toBe(1)
        ->and($stats['completedToday'])->toBe(1)
        ->and($stats['projects'])->toBe(1);
});

it('summarizes support open tickets by priority and SLA risk', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Urgent, 'sla_due_at' => now()->addHour()]);
    Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Low, 'sla_due_at' => now()->addWeek()]);
    Ticket::factory()->create(['status' => TicketStatus::Resolved, 'priority' => TicketPriority::High]);

    $stats = $this->metrics->supportStats($support);

    expect($stats['open_total'])->toBe(2)
        ->and($stats['sla_at_risk'])->toBe(1)
        ->and($stats['open_by_priority']['Urgent'])->toBe(1);
});
