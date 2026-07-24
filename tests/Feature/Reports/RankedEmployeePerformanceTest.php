<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Services\ReportMetrics;

beforeEach(function () {
    $this->metrics = app(ReportMetrics::class);
});

it('ranks Sales reps by leads converted, highest first', function () {
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    $bob = User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    $carol = User::factory()->role(UserRole::Sales)->create(['name' => 'Carol']);

    Lead::factory()->count(3)->create(['owner_id' => $alice->id, 'converted_at' => now()]);
    Lead::factory()->count(2)->create(['owner_id' => $bob->id, 'converted_at' => now()]);
    Lead::factory()->count(1)->create(['owner_id' => $carol->id, 'converted_at' => now()]);

    $rows = $this->metrics->rankedEmployeePerformance(now()->startOfMonth(), now()->endOfMonth());
    $byName = $rows->keyBy('user');

    expect($byName['Alice']['rank'])->toBe(1)
        ->and($byName['Alice']['score'])->toBe(70)
        ->and($byName['Bob']['rank'])->toBe(2)
        ->and($byName['Bob']['score'])->toBe(50)
        ->and($byName['Carol']['rank'])->toBe(3)
        ->and($byName['Carol']['score'])->toBe(30)
        ->and($byName['Carol']['weakest_metric'])->toBe('leads_converted')
        ->and(collect([$byName['Alice'], $byName['Bob'], $byName['Carol']])->pluck('role_group_size')->unique()->all())->toBe([3]);
});

it('excludes Admin and Manager from ranking entirely', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();

    $rows = $this->metrics->rankedEmployeePerformance(now()->startOfMonth(), now()->endOfMonth());
    $byName = $rows->keyBy('user');

    expect($byName[$admin->name]['score'])->toBeNull()
        ->and($byName[$admin->name]['rank'])->toBeNull()
        ->and($byName[$admin->name]['ranking_note'])->toBeNull()
        ->and($byName[$manager->name]['score'])->toBeNull()
        ->and($byName[$manager->name]['rank'])->toBeNull();
});

it('shows a ranking note instead of a fabricated rank when a role has fewer than 2 people', function () {
    $onlyAccountant = User::factory()->role(UserRole::Accounts)->create();

    $rows = $this->metrics->rankedEmployeePerformance(now()->startOfMonth(), now()->endOfMonth());
    $row = $rows->firstWhere('user_id', $onlyAccountant->id);

    expect($row['rank'])->toBeNull()
        ->and($row['score'])->toBeNull()
        ->and($row['role_group_size'])->toBe(1)
        ->and($row['ranking_note'])->toBe('Not enough peers in this role yet to compare.');
});

it('never selects a zero-weight metric as the weakest area for that role', function () {
    // leads_converted has weight 0 for Support — even though a Support user
    // converts zero leads (the lowest possible value), it must never be
    // flagged as their weakest area.
    $support1 = User::factory()->role(UserRole::Support)->create();
    $support2 = User::factory()->role(UserRole::Support)->create();

    $rows = $this->metrics->rankedEmployeePerformance(now()->startOfMonth(), now()->endOfMonth());

    foreach ($rows->where('role_value', 'support') as $row) {
        expect($row['weakest_metric'])->not->toBe('leads_converted');
    }
});
