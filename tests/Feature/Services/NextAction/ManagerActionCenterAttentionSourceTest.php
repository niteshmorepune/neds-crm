<?php

use App\Enums\InvoiceStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NextActionSnooze;
use App\Models\Task;
use App\Models\User;
use App\Services\NextAction\ManagerActionCenterAttentionSource;

function managerActionCenterAttentionSource(): ManagerActionCenterAttentionSource
{
    return app(ManagerActionCenterAttentionSource::class);
}

it('returns null for a non-Admin/Manager user', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    expect(managerActionCenterAttentionSource()->next($sales))->toBeNull();
});

it('returns null when nothing needs attention', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    expect(managerActionCenterAttentionSource()->next($admin))->toBeNull();
});

it('prompts an Admin with a total count and breakdown when signals are pending', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);
    Task::factory()->create(['assignee_id' => $admin->id, 'status' => TaskStatus::Todo, 'due_date' => now()->subDay()]);

    $action = managerActionCenterAttentionSource()->next($admin);

    expect($action)->not->toBeNull();
    // A customer with overdue invoices is also flagged by ClientRadarService
    // as "at risk" — a real, intentional overlap between two of
    // ManagerActionCenterMetrics' own signals, not double-counting by this
    // source: 2 overdue invoices + 1 at-risk client + 1 overdue task.
    expect($action->title)->toBe('4 items need your attention');
    expect($action->body)->toContain('2 overdue invoices');
    expect($action->body)->toContain('1 overdue tasks');
    expect($action->body)->toContain('1 clients needing attention');
    expect($action->actionUrl)->toBe(route('manager-action-center.index'));
});

it('also prompts a Manager (not just Admin)', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    expect(managerActionCenterAttentionSource()->next($manager))->not->toBeNull();
});

it('uses singular wording for exactly one item', function () {
    // An overdue task with no customer/invoice involved at all, to avoid
    // ClientRadarService's own overlap with the overdue-invoices signal
    // (see the note in the test above) — this fixture only ever trips
    // exactly one of ManagerActionCenterMetrics' six signals.
    $admin = User::factory()->role(UserRole::Admin)->create();
    Task::factory()->create(['assignee_id' => $admin->id, 'status' => TaskStatus::Todo, 'due_date' => now()->subDay()]);

    expect(managerActionCenterAttentionSource()->next($admin)->title)->toBe('1 item needs your attention');
});

it('excludes when snoozed, but includes again once the snooze expires', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    NextActionSnooze::create([
        'user_id' => $admin->id,
        'source_key' => 'manager_action_center_attention',
        'subject_type' => 'manager_action_center',
        'subject_id' => 1,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(managerActionCenterAttentionSource()->next($admin))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(managerActionCenterAttentionSource()->next($admin))->not->toBeNull();
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    managerActionCenterAttentionSource()->complete($admin, 1);
})->throws(RuntimeException::class);
