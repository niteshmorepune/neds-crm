<?php

use App\Enums\LeadReassignmentReason;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadReassignment;
use App\Models\User;
use App\Services\ReassignmentMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(ReassignmentMetrics::class);
});

function logReassignment(?User $from, User $to, LeadReassignmentReason $reason, ?Carbon $at = null): LeadReassignment
{
    $lead = Lead::factory()->create();

    return LeadReassignment::create([
        'lead_id' => $lead->id,
        'from_user_id' => $from?->id,
        'to_user_id' => $to->id,
        'reassigned_by' => $to->id,
        'reason' => $reason,
        'created_at' => $at ?? now(),
    ]);
}

it('counts leads reassigned away from a rep, with a reasons breakdown', function () {
    $kiran = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);
    $mohit = User::factory()->role(UserRole::Sales)->create(['name' => 'Mohit Patil']);

    logReassignment($kiran, $mohit, LeadReassignmentReason::Rebalancing);
    logReassignment($kiran, $mohit, LeadReassignmentReason::Rebalancing);
    logReassignment($kiran, $mohit, LeadReassignmentReason::OnLeave);

    $data = $this->metrics->reassignmentAnalytics(now()->subMonth(), now()->addMonth());

    $kiranRow = collect($data['rows'])->firstWhere('user', 'Kiran Katte');
    expect($kiranRow['reassigned_away_count'])->toBe(3);
    $rebalancing = collect($kiranRow['reassigned_away_reasons'])->firstWhere('reason', 'rebalancing');
    expect($rebalancing['count'])->toBe(2);
    $onLeave = collect($kiranRow['reassigned_away_reasons'])->firstWhere('reason', 'on_leave');
    expect($onLeave['count'])->toBe(1);
});

it('counts leads reassigned to a rep separately from leads reassigned away', function () {
    $kiran = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);
    $mohit = User::factory()->role(UserRole::Sales)->create(['name' => 'Mohit Patil']);

    logReassignment($kiran, $mohit, LeadReassignmentReason::Rebalancing);
    logReassignment($kiran, $mohit, LeadReassignmentReason::Rebalancing);

    $data = $this->metrics->reassignmentAnalytics(now()->subMonth(), now()->addMonth());

    $mohitRow = collect($data['rows'])->firstWhere('user', 'Mohit Patil');
    expect($mohitRow['reassigned_to_count'])->toBe(2)
        ->and($mohitRow['reassigned_away_count'])->toBe(0)
        ->and($mohitRow['reassigned_away_reasons'])->toBe([]);
});

it('shows every active Sales rep with a clean zero, not a missing row, when they have no reassignments', function () {
    $untouched = User::factory()->role(UserRole::Sales)->create(['name' => 'Untouched Rep']);

    $data = $this->metrics->reassignmentAnalytics(now()->subMonth(), now()->addMonth());

    $row = collect($data['rows'])->firstWhere('user', 'Untouched Rep');
    expect($row)->not->toBeNull()
        ->and($row['reassigned_away_count'])->toBe(0)
        ->and($row['reassigned_to_count'])->toBe(0);
});

it('does not show an inactive Sales rep with no reassignment activity', function () {
    User::factory()->role(UserRole::Sales)->create(['name' => 'Former Rep', 'is_active' => false]);

    $data = $this->metrics->reassignmentAnalytics(now()->subMonth(), now()->addMonth());

    expect(collect($data['rows'])->firstWhere('user', 'Former Rep'))->toBeNull();
});

it('still surfaces a non-Sales party that appears in real reassignment data, even though they are outside the default Sales population', function () {
    $manager = User::factory()->role(UserRole::Manager)->create(['name' => 'Manali Deshpande']);
    $sales = User::factory()->role(UserRole::Sales)->create();

    logReassignment($sales, $manager, LeadReassignmentReason::Other);

    $data = $this->metrics->reassignmentAnalytics(now()->subMonth(), now()->addMonth());

    $managerRow = collect($data['rows'])->firstWhere('user', 'Manali Deshpande');
    expect($managerRow)->not->toBeNull()->and($managerRow['reassigned_to_count'])->toBe(1);
});

it('handles an unowned lead being reassigned (no from_user_id) without crashing', function () {
    $mohit = User::factory()->role(UserRole::Sales)->create(['name' => 'Mohit Patil']);

    logReassignment(null, $mohit, LeadReassignmentReason::Rebalancing);

    $data = $this->metrics->reassignmentAnalytics(now()->subMonth(), now()->addMonth());

    $mohitRow = collect($data['rows'])->firstWhere('user', 'Mohit Patil');
    expect($mohitRow['reassigned_to_count'])->toBe(1);
});

it('excludes reassignments outside the requested date range', function () {
    $kiran = User::factory()->role(UserRole::Sales)->create();
    $mohit = User::factory()->role(UserRole::Sales)->create();

    logReassignment($kiran, $mohit, LeadReassignmentReason::Rebalancing, at: now()->subMonths(3));

    $data = $this->metrics->reassignmentAnalytics(now()->startOfMonth(), now()->endOfMonth());

    expect($data['total'])->toBe(0);
});

describe('reports/reassignment-analytics route', function () {
    it('is reachable by Admin and Manager', function () {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get(route('reports.reassignment-analytics'))->assertOk()->assertSee('Reassignment Analytics');

        $manager = User::factory()->role(UserRole::Manager)->create();
        $this->actingAs($manager)->get(route('reports.reassignment-analytics'))->assertOk();
    });

    it('is forbidden for Sales', function () {
        $sales = User::factory()->role(UserRole::Sales)->create();
        $this->actingAs($sales)->get(route('reports.reassignment-analytics'))->assertForbidden();
    });

    it('exports a CSV with a header row', function () {
        $kiran = User::factory()->role(UserRole::Sales)->create();
        $mohit = User::factory()->role(UserRole::Sales)->create();
        logReassignment($kiran, $mohit, LeadReassignmentReason::Rebalancing);
        $admin = User::factory()->role(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('reports.reassignment-analytics.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        expect($response->streamedContent())->toContain('Rep')->toContain('Reassigned away');
    });
});
