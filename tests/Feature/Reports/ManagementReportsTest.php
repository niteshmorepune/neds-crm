<?php

use App\Actions\ConvertLead;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Services\ReportMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(ReportMetrics::class);
});

it('stamps completed_at when a task is marked done and clears it when reopened', function () {
    $task = Task::factory()->create(['status' => TaskStatus::Todo]);
    expect($task->completed_at)->toBeNull();

    $task->update(['status' => TaskStatus::Done]);
    expect($task->refresh()->completed_at)->not->toBeNull();

    $task->update(['status' => TaskStatus::InProgress]);
    expect($task->refresh()->completed_at)->toBeNull();
});

it('counts a converted lead under its owner in the performance report', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($sales->id)->create();
    $this->actingAs($sales);
    app(ConvertLead::class)->handle($lead);

    $rows = $this->metrics->employeePerformance(now()->startOfMonth(), now()->endOfMonth());
    $row = collect($rows)->firstWhere('user', $sales->name);

    expect($row['leads_converted'])->toBe(1);
});

it('measures tasks completed, on-time %, and calls for the period', function () {
    $user = User::factory()->role(UserRole::Support)->create();
    // On-time completion (due today, completed today).
    Task::factory()->create(['assignee_id' => $user->id, 'due_date' => now(), 'status' => TaskStatus::Done]);
    // Late completion (due 2 days ago, completed today).
    $late = Task::factory()->create(['assignee_id' => $user->id, 'due_date' => now()->subDays(2), 'status' => TaskStatus::Todo]);
    $late->update(['status' => TaskStatus::Done]);
    CallLog::factory()->count(2)->create(['user_id' => $user->id, 'called_at' => now()]);

    $row = collect($this->metrics->employeePerformance(now()->startOfMonth(), now()->endOfMonth()))
        ->firstWhere('user', $user->name);

    expect($row['tasks_completed'])->toBe(2)
        ->and($row['on_time_pct'])->toBe(50)
        ->and($row['calls_made'])->toBe(2);
});

it('calculates attendance % against working days not just record count', function () {
    $user = User::factory()->role(UserRole::Support)->create();
    // Only 1 present record in a month that has many working days.
    Attendance::factory()->create([
        'user_id' => $user->id,
        'date' => now()->startOfMonth()->toDateString(),
        'status' => AttendanceStatus::Present,
        'check_in_at' => now()->startOfMonth()->setTime(9, 0),
    ]);

    $row = collect($this->metrics->employeePerformance(now()->startOfMonth(), now()->endOfMonth()))
        ->firstWhere('user', $user->name);

    // 1 day present out of ~20 working days — must be well below 100%.
    expect($row['attendance_pct'])->toBeLessThan(20);
});

it('counts overdue unfinished tasks against on-time %', function () {
    $user = User::factory()->role(UserRole::Support)->create();
    // One task due this month, never completed.
    Task::factory()->create(['assignee_id' => $user->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Todo]);

    $row = collect($this->metrics->employeePerformance(now()->startOfMonth(), now()->endOfMonth()))
        ->firstWhere('user', $user->name);

    expect($row['on_time_pct'])->toBe(0);
});

it('splits revenue into recurring and one-time', function () {
    $template = RecurringInvoice::factory()->create();
    $issue = now()->startOfMonth()->addDays(2);
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'issue_date' => $issue, 'total' => 1000000, 'recurring_invoice_id' => $template->id]);
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'issue_date' => $issue, 'total' => 500000, 'recurring_invoice_id' => null]);
    // Draft is excluded.
    Invoice::factory()->create(['status' => InvoiceStatus::Draft, 'issue_date' => $issue, 'total' => 999999]);

    $fyStart = now()->month >= 4 ? now()->year : now()->year - 1;
    $data = $this->metrics->revenue(Carbon::create($fyStart, 4, 1), Carbon::create($fyStart + 1, 3, 31)->endOfDay());

    expect($data['total'])->toBe(1500000)
        ->and($data['recurring'])->toBe(1000000)
        ->and($data['one_time'])->toBe(500000);
});

it('groups revenue by service via the linked deal', function () {
    $service = Service::factory()->create(['name' => 'SEO']);
    $deal = Deal::factory()->create(['service_id' => $service->id]);
    Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'issue_date' => now()->startOfMonth(), 'total' => 700000, 'deal_id' => $deal->id]);

    $fyStart = now()->month >= 4 ? now()->year : now()->year - 1;
    $data = $this->metrics->revenue(Carbon::create($fyStart, 4, 1), Carbon::create($fyStart + 1, 3, 31)->endOfDay());

    expect(collect($data['by_service'])->firstWhere('name', 'SEO')['total'])->toBe(700000);
});

it('lets a manager view the reports but forbids a sales rep', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($manager)->get(route('reports.employee-performance'))->assertOk()->assertSee('Employee Performance Report');
    $this->actingAs($manager)->get(route('reports.revenue'))->assertOk()->assertSee('Revenue Report');
    $this->actingAs($sales)->get(route('reports.employee-performance'))->assertForbidden();
});

it('lets accounts view revenue but not the performance report', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('reports.revenue'))->assertOk();
    $this->actingAs($accounts)->get(route('reports.employee-performance'))->assertForbidden();
});

it('exports the reports as CSV', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $perf = $this->actingAs($manager)->get(route('reports.employee-performance.export'));
    $perf->assertOk();
    expect($perf->headers->get('content-type'))->toContain('text/csv');

    $rev = $this->actingAs($manager)->get(route('reports.revenue.export'));
    $rev->assertOk();
    expect($rev->headers->get('content-type'))->toContain('text/csv');
});
