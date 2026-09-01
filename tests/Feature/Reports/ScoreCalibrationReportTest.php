<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Services\ScoreCalibrationMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(ScoreCalibrationMetrics::class);
});

/** Transitions a Lead to Lost via a real update() (not mass-assigned at create) so Lead::booted()'s saving() hook actually stamps lost_at, exactly as it would in production. */
function loseLead(array $attributes = [], ?Carbon $lostAt = null): Lead
{
    $lead = Lead::factory()->create($attributes + ['status' => LeadStatus::New]);
    $lead->update(['status' => LeadStatus::Lost]);

    if ($lostAt !== null) {
        $lead->forceFill(['lost_at' => $lostAt])->saveQuietly();
    }

    return $lead->fresh();
}

function convertLead(array $attributes = [], ?Carbon $convertedAt = null): Lead
{
    return Lead::factory()->create($attributes + [
        'status' => LeadStatus::Converted,
        'converted_at' => $convertedAt ?? now(),
    ]);
}

it('stamps lost_at when a lead transitions to Lost, and clears it if reopened', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    expect($lead->lost_at)->toBeNull();

    $lead->update(['status' => LeadStatus::Lost]);
    expect($lead->refresh()->lost_at)->not->toBeNull();

    $lead->update(['status' => LeadStatus::Contacted]);
    expect($lead->refresh()->lost_at)->toBeNull();
});

it('does not overwrite an existing lost_at on an unrelated later edit', function () {
    $lead = loseLead();
    $original = $lead->lost_at;

    $lead->update(['company' => 'Renamed Co']);

    expect($lead->refresh()->lost_at->equalTo($original))->toBeTrue();
});

it('buckets closed leads into Cold/Warm/Hot by ai_score, with conversion rate per bucket', function () {
    convertLead(['ai_score' => 85]); // hot, converted
    loseLead(['ai_score' => 90]); // hot, lost
    convertLead(['ai_score' => 50]); // warm, converted
    loseLead(['ai_score' => 10]); // cold, lost

    $data = $this->metrics->scoreCalibration(now()->subMonth(), now()->addMonth());

    expect($data['total'])->toBe(4);

    $hot = collect($data['buckets'])->firstWhere('band', 'hot');
    expect($hot['total'])->toBe(2)
        ->and($hot['converted'])->toBe(1)
        ->and($hot['lost'])->toBe(1)
        ->and($hot['conversion_rate'])->toBe(50);

    $warm = collect($data['buckets'])->firstWhere('band', 'warm');
    expect($warm['total'])->toBe(1)->and($warm['conversion_rate'])->toBe(100);

    $cold = collect($data['buckets'])->firstWhere('band', 'cold');
    expect($cold['total'])->toBe(1)->and($cold['conversion_rate'])->toBe(0);
});

it('always shows all four bands even when empty, rather than a missing row', function () {
    $data = $this->metrics->scoreCalibration(now()->subMonth(), now()->addMonth());

    $bands = collect($data['buckets'])->pluck('band')->all();
    expect($bands)->toBe(['hot', 'warm', 'cold', 'no_score']);
    foreach ($data['buckets'] as $b) {
        expect($b['total'])->toBe(0)->and($b['conversion_rate'])->toBe(0);
    }
});

it('groups a lead with no ai_score under No score data without crashing or miscounting', function () {
    convertLead(['ai_score' => null]);

    $data = $this->metrics->scoreCalibration(now()->subMonth(), now()->addMonth());

    expect($data['total'])->toBe(1);
    $noScore = collect($data['buckets'])->firstWhere('band', 'no_score');
    expect($noScore['total'])->toBe(1)->and($noScore['label'])->toBe('No score data');
});

it('excludes leads that closed outside the requested date range', function () {
    convertLead(['ai_score' => 80], convertedAt: now()->subMonths(3));
    convertLead(['ai_score' => 80], convertedAt: now());

    $data = $this->metrics->scoreCalibration(now()->startOfMonth(), now()->endOfMonth());

    expect($data['total'])->toBe(1);
});

it('excludes leads that are still open, even with a high score', function () {
    Lead::factory()->create(['status' => LeadStatus::Contacted, 'ai_score' => 95]);

    $data = $this->metrics->scoreCalibration(now()->subMonth(), now()->addMonth());

    expect($data['total'])->toBe(0);
});

it('computes average and median days-to-close per outcome within a bucket', function () {
    $convertedAt = now();
    convertLead(['ai_score' => 80, 'created_at' => $convertedAt->copy()->subDays(10)], convertedAt: $convertedAt);
    convertLead(['ai_score' => 80, 'created_at' => $convertedAt->copy()->subDays(20)], convertedAt: $convertedAt);

    $data = $this->metrics->scoreCalibration(now()->subMonth(), now()->addMonth());

    $hot = collect($data['buckets'])->firstWhere('band', 'hot');
    expect($hot['avg_days_to_close_converted'])->toBe(15) // (10+20)/2
        ->and($hot['median_days_to_close_converted'])->toBe(15);
});

describe('reports/score-calibration route', function () {
    it('is reachable by Admin and Manager', function () {
        convertLead(['ai_score' => 70]);

        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get(route('reports.score-calibration'))->assertOk()->assertSee('Score Calibration');

        $manager = User::factory()->role(UserRole::Manager)->create();
        $this->actingAs($manager)->get(route('reports.score-calibration'))->assertOk();
    });

    it('is forbidden for Sales', function () {
        $sales = User::factory()->role(UserRole::Sales)->create();
        $this->actingAs($sales)->get(route('reports.score-calibration'))->assertForbidden();
    });

    it('exports a CSV with a header row and one row per band', function () {
        convertLead(['ai_score' => 70]);
        $admin = User::factory()->role(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('reports.score-calibration.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        expect($csv)->toContain('Score band')
            ->toContain('Hot')->toContain('Warm')->toContain('Cold')->toContain('No score data');
    });

    it('respects the month filter', function () {
        convertLead(['ai_score' => 70], convertedAt: now()->subMonths(2));
        $admin = User::factory()->role(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('reports.score-calibration', ['month' => now()->format('Y-m')]));

        $response->assertOk()->assertSee('No leads closed in this period.');
    });
});
