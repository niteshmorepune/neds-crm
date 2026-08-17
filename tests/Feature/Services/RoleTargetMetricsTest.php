<?php

use App\Enums\TargetMetric;
use App\Enums\TaskStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\RoleTarget;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\RoleTargetMetrics;

beforeEach(function () {
    $this->metrics = app(RoleTargetMetrics::class);
});

describe('TargetMetric::forRole()', function () {
    it('maps each non-Sales role to its one KRA metric', function () {
        expect(TargetMetric::forRole(UserRole::Support))->toBe(TargetMetric::TicketsResolved)
            ->and(TargetMetric::forRole(UserRole::Accounts))->toBe(TargetMetric::CollectionsRecorded)
            ->and(TargetMetric::forRole(UserRole::Intern))->toBe(TargetMetric::TasksCompleted)
            ->and(TargetMetric::forRole(UserRole::Telecaller))->toBe(TargetMetric::CallsMade);
    });

    it('returns null for Sales, Admin, and Manager', function () {
        expect(TargetMetric::forRole(UserRole::Sales))->toBeNull()
            ->and(TargetMetric::forRole(UserRole::Admin))->toBeNull()
            ->and(TargetMetric::forRole(UserRole::Manager))->toBeNull();
    });
});

describe('actualValue()', function () {
    it('counts tickets resolved or closed with resolved_at in range for Support', function () {
        $user = User::factory()->role(UserRole::Support)->create();
        Ticket::factory()->create(['assignee_id' => $user->id, 'status' => TicketStatus::Resolved, 'resolved_at' => now()]);
        Ticket::factory()->create(['assignee_id' => $user->id, 'status' => TicketStatus::Closed, 'resolved_at' => now()]);
        Ticket::factory()->create(['assignee_id' => $user->id, 'status' => TicketStatus::Open, 'resolved_at' => null]);

        $count = $this->metrics->actualValue($user, TargetMetric::TicketsResolved, now()->startOfMonth(), now()->endOfMonth());

        expect($count)->toBe(2);
    });

    it('sums payments recorded by the user for Accounts', function () {
        $user = User::factory()->role(UserRole::Accounts)->create();
        $invoice = Invoice::factory()->create();
        Payment::factory()->create(['invoice_id' => $invoice->id, 'recorded_by' => $user->id, 'amount' => 50000, 'paid_on' => now()]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'recorded_by' => $user->id, 'amount' => 25000, 'paid_on' => now()]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'recorded_by' => User::factory()->create()->id, 'amount' => 99999, 'paid_on' => now()]);

        $total = $this->metrics->actualValue($user, TargetMetric::CollectionsRecorded, now()->startOfMonth(), now()->endOfMonth());

        expect($total)->toBe(75000);
    });

    it('counts tasks completed by the user for Intern', function () {
        $user = User::factory()->role(UserRole::Intern)->create();
        Task::factory()->assignedTo($user->id)->status(TaskStatus::Done)->create(['completed_at' => now()]);
        Task::factory()->assignedTo($user->id)->status(TaskStatus::Todo)->create();

        $count = $this->metrics->actualValue($user, TargetMetric::TasksCompleted, now()->startOfMonth(), now()->endOfMonth());

        expect($count)->toBe(1);
    });

    it('counts calls made by the user for Telecaller', function () {
        $user = User::factory()->role(UserRole::Telecaller)->create();
        CallLog::factory()->count(3)->create(['user_id' => $user->id, 'called_at' => now()]);

        $count = $this->metrics->actualValue($user, TargetMetric::CallsMade, now()->startOfMonth(), now()->endOfMonth());

        expect($count)->toBe(3);
    });
});

describe('progressForUser()', function () {
    it('returns null for a role with no mapped KRA metric', function () {
        $sales = User::factory()->role(UserRole::Sales)->create();

        expect($this->metrics->progressForUser($sales))->toBeNull();
    });

    it('computes target/actual/pct for a role with a target set', function () {
        $user = User::factory()->role(UserRole::Telecaller)->create();
        RoleTarget::factory()->forUser($user->id, TargetMetric::CallsMade)->create(['target_value' => 10]);
        CallLog::factory()->count(4)->create(['user_id' => $user->id, 'called_at' => now()]);

        $progress = $this->metrics->progressForUser($user);

        expect($progress)->toBe(['metric' => TargetMetric::CallsMade, 'target' => 10, 'actual' => 4, 'pct' => 40]);
    });

    it('returns a null target with the actual still computed when no target is set', function () {
        $user = User::factory()->role(UserRole::Telecaller)->create();
        CallLog::factory()->count(2)->create(['user_id' => $user->id, 'called_at' => now()]);

        $progress = $this->metrics->progressForUser($user);

        expect($progress)->toBe(['metric' => TargetMetric::CallsMade, 'target' => null, 'actual' => 2, 'pct' => null]);
    });
});

describe('teamRows()', function () {
    it('returns one row per active telecaller plus a role-wide roll-up', function () {
        $active = User::factory()->role(UserRole::Telecaller)->create(['name' => 'Active Caller', 'is_active' => true]);
        $inactive = User::factory()->role(UserRole::Telecaller)->create(['name' => 'Inactive Caller', 'is_active' => false]);
        CallLog::factory()->count(5)->create(['user_id' => $active->id, 'called_at' => now()]);
        CallLog::factory()->count(9)->create(['user_id' => $inactive->id, 'called_at' => now()]);
        RoleTarget::factory()->create(['user_id' => null, 'metric' => TargetMetric::CallsMade, 'target_value' => 20]);

        $result = $this->metrics->teamRows(UserRole::Telecaller);

        expect($result['metric'])->toBe(TargetMetric::CallsMade)
            ->and($result['rows'])->toHaveCount(1)
            ->and($result['rows']->first()['user']->name)->toBe('Active Caller')
            ->and($result['rows']->first()['actual'])->toBe(5)
            ->and($result['roleWide'])->toBe(['metric' => TargetMetric::CallsMade, 'target' => 20, 'actual' => 5, 'pct' => 25]);
    });
});
