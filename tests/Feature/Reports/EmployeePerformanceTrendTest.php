<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Services\ReportMetrics;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->metrics = app(ReportMetrics::class);
});

it('computes a positive tasks/leads delta when this month beats last month', function () {
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);

    Lead::factory()->count(2)->create(['owner_id' => $alice->id, 'converted_at' => now()->subMonthNoOverflow()]);
    Lead::factory()->count(5)->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    $rows = $this->metrics->employeePerformanceTrend(now()->startOfMonth(), now()->endOfMonth());
    $alice = $rows->firstWhere('user', 'Alice');

    expect($alice['leads_converted'])->toBe(5)
        ->and($alice['trend']['leads_converted'])->toBe(3);
});

it('computes a negative delta when this month is worse than last month', function () {
    $bob = User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);

    Lead::factory()->count(6)->create(['owner_id' => $bob->id, 'converted_at' => now()->subMonthNoOverflow()]);
    Lead::factory()->count(2)->create(['owner_id' => $bob->id, 'converted_at' => now()]);

    $rows = $this->metrics->employeePerformanceTrend(now()->startOfMonth(), now()->endOfMonth());
    $bob = $rows->firstWhere('user', 'Bob');

    expect($bob['trend']['leads_converted'])->toBe(-4);
});

it('leaves the trend null for a metric with no denominator in the prior month, rather than erroring', function () {
    $carol = User::factory()->role(UserRole::Support)->create(['name' => 'Carol']);
    // No attendance records at all last month (e.g. joined this month) — attendance_pct is null both months.

    $rows = $this->metrics->employeePerformanceTrend(now()->startOfMonth(), now()->endOfMonth());
    $carol = $rows->firstWhere('user', 'Carol');

    expect($carol['attendance_pct'])->toBeNull()
        ->and($carol['trend']['attendance_pct'])->toBeNull();
});

it('reflects the composite score delta driven by the underlying metric change', function () {
    $dave = User::factory()->role(UserRole::Sales)->create(['name' => 'Dave']);
    $erin = User::factory()->role(UserRole::Sales)->create(['name' => 'Erin']);

    Lead::factory()->count(1)->create(['owner_id' => $dave->id, 'converted_at' => now()->subMonthNoOverflow()]);
    Lead::factory()->count(1)->create(['owner_id' => $erin->id, 'converted_at' => now()->subMonthNoOverflow()]);
    Lead::factory()->count(10)->create(['owner_id' => $dave->id, 'converted_at' => now()]);
    Lead::factory()->count(1)->create(['owner_id' => $erin->id, 'converted_at' => now()]);

    $rows = $this->metrics->employeePerformanceTrend(now()->startOfMonth(), now()->endOfMonth());
    $dave = $rows->firstWhere('user', 'Dave');

    expect($dave['trend']['score'])->toBeGreaterThan(0);
});

it('shows a trend delta indicator on the Employee Performance report page', function () {
    $this->seed(MenuItemsSeeder::class);
    $manager = User::factory()->role(UserRole::Manager)->create();
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    $bob = User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);

    Lead::factory()->count(1)->create(['owner_id' => $alice->id, 'converted_at' => now()->subMonthNoOverflow()]);
    Lead::factory()->count(1)->create(['owner_id' => $bob->id, 'converted_at' => now()->subMonthNoOverflow()]);
    Lead::factory()->count(4)->create(['owner_id' => $alice->id, 'converted_at' => now()]);
    Lead::factory()->count(1)->create(['owner_id' => $bob->id, 'converted_at' => now()]);

    $this->actingAs($manager)->get(route('reports.employee-performance'))
        ->assertOk()
        ->assertSeeInOrder(['Alice', '4', '(+3)']);
});
