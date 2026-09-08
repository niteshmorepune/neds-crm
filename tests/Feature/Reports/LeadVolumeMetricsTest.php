<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadVolumeMetrics;

beforeEach(function () {
    $this->metrics = app(LeadVolumeMetrics::class);
});

it('counts leads per day and rolls up an overall total', function () {
    $day1 = now()->startOfMonth()->addDays(2);
    $day2 = now()->startOfMonth()->addDays(5);
    Lead::factory()->count(2)->create(['created_at' => $day1]);
    Lead::factory()->create(['created_at' => $day2]);

    $trend = $this->metrics->dailyTrend(now()->startOfMonth(), now()->endOfMonth());

    expect($trend['totals'][$day1->toDateString()])->toBe(2)
        ->and($trend['totals'][$day2->toDateString()])->toBe(1)
        ->and(array_sum($trend['totals']))->toBe(3);
});

it('splits the same day by owner and separately by telecaller', function () {
    $day = now()->startOfMonth()->addDay();

    // Created before any Sales/Telecaller user exists, so LeadObserver's
    // round-robin auto-assign has nobody to claim them onto — see
    // [[feedback-gotchas]]: it claims a null-FK fixture the moment an
    // eligible user exists, corrupting an "unassigned" expectation.
    Lead::factory()->count(2)->create(['created_at' => $day]);

    $kiran = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);
    $rohit = User::factory()->role(UserRole::Telecaller)->create(['name' => 'Rohit Dhulasavant']);

    Lead::factory()->create(['created_at' => $day, 'owner_id' => $kiran->id, 'telecaller_id' => $rohit->id]);

    $trend = $this->metrics->dailyTrend(now()->startOfMonth(), now()->endOfMonth());
    $dayKey = $day->toDateString();

    expect($trend['by_owner'][$dayKey][(string) $kiran->id])->toBe(1)
        ->and($trend['by_owner'][$dayKey]['unassigned'])->toBe(2)
        ->and($trend['by_telecaller'][$dayKey][(string) $rohit->id])->toBe(1)
        ->and($trend['by_telecaller'][$dayKey]['unassigned'])->toBe(2);
});

it('builds owner columns alphabetically by name with Unassigned last', function () {
    $day = now()->startOfMonth()->addDay();

    // See the round-robin-auto-assign gotcha noted above — created first,
    // before either Sales user exists.
    Lead::factory()->create(['created_at' => $day, 'owner_id' => null]);

    $mohit = User::factory()->role(UserRole::Sales)->create(['name' => 'Mohit Patil']);
    $kiran = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);

    Lead::factory()->create(['created_at' => $day, 'owner_id' => $mohit->id]);
    Lead::factory()->create(['created_at' => $day, 'owner_id' => $kiran->id]);

    $trend = $this->metrics->dailyTrend(now()->startOfMonth(), now()->endOfMonth());

    expect(array_column($trend['owner_columns'], 'label'))->toBe(['Kiran Katte', 'Mohit Patil', 'Unassigned']);
});

it('omits the Unassigned column entirely when every lead in range has an owner', function () {
    $kiran = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['created_at' => now()->startOfMonth()->addDay(), 'owner_id' => $kiran->id]);

    $trend = $this->metrics->dailyTrend(now()->startOfMonth(), now()->endOfMonth());

    expect(array_column($trend['owner_columns'], 'key'))->not->toContain('unassigned');
});

it('excludes leads created outside the requested range', function () {
    Lead::factory()->create(['created_at' => now()->subMonths(2)]);

    $trend = $this->metrics->dailyTrend(now()->startOfMonth(), now()->endOfMonth());

    expect(array_sum($trend['totals']))->toBe(0);
});
