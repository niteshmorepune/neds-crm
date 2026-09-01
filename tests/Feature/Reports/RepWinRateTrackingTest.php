<?php

use App\Enums\DealStage;
use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\RepWinRateSnapshot;
use App\Models\User;
use App\Services\RepWinRateMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-01 07:55:00'));
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(RepWinRateMetrics::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Creates an already-closed Deal with stage_changed_at set to a specific date, bypassing moveToStage() like the rest of this app's tests do. */
function closedDeal(int $ownerId, DealStage $stage, ?Carbon $closedAt = null, array $attributes = []): Deal
{
    $deal = Deal::factory()->ownedBy($ownerId)->stage($stage)->create($attributes);

    if ($closedAt !== null) {
        $deal->forceFill(['stage_changed_at' => $closedAt])->saveQuietly();
    }

    return $deal;
}

describe('RepWinRateMetrics::calculate()', function () {
    it('computes overall win rate per rep from Won/Lost deals closed in the period', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-10'));
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-15'));
        closedDeal($rep->id, DealStage::Lost, Carbon::parse('2026-07-20'));

        $rows = $this->metrics->calculate(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $row = collect($rows)->firstWhere('user_id', $rep->id);
        expect($row['overall']['won_count'])->toBe(2)
            ->and($row['overall']['lost_count'])->toBe(1)
            ->and($row['overall']['win_rate'])->toBe(67); // 2/3 rounded
    });

    it('shows a clean N/A (null), not an error, for a rep with zero closed deals in the period', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();

        $rows = $this->metrics->calculate(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $row = collect($rows)->firstWhere('user_id', $rep->id);
        expect($row)->not->toBeNull()
            ->and($row['overall']['won_count'])->toBe(0)
            ->and($row['overall']['lost_count'])->toBe(0)
            ->and($row['overall']['win_rate'])->toBeNull()
            ->and($row['by_source'])->toBe([])
            ->and($row['by_score_band'])->toBe([]);
    });

    it('breaks down by lead source, summing correctly against the rep overall rate', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        $metaLead = Lead::factory()->create(['source' => LeadSource::MetaAds]);
        $siteLead = Lead::factory()->create(['source' => LeadSource::Website]);

        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-10'), ['lead_id' => $metaLead->id]);
        closedDeal($rep->id, DealStage::Lost, Carbon::parse('2026-07-11'), ['lead_id' => $metaLead->id]);
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-12'), ['lead_id' => $siteLead->id]);
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-13')); // no lead -> Direct

        $rows = $this->metrics->calculate(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));
        $row = collect($rows)->firstWhere('user_id', $rep->id);

        expect($row['overall']['won_count'])->toBe(3)->and($row['overall']['lost_count'])->toBe(1);

        $bySourceWon = collect($row['by_source'])->sum('won_count');
        $bySourceLost = collect($row['by_source'])->sum('lost_count');
        expect($bySourceWon)->toBe(3)->and($bySourceLost)->toBe(1);

        $meta = collect($row['by_source'])->firstWhere('dimension_value', 'meta_ads');
        expect($meta['won_count'])->toBe(1)->and($meta['lost_count'])->toBe(1)->and($meta['win_rate'])->toBe(50);

        $direct = collect($row['by_source'])->firstWhere('dimension_value', 'direct');
        expect($direct['label'])->toBe('Direct (no lead)')->and($direct['won_count'])->toBe(1);
    });

    it('breaks down by the originating lead\'s score band', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        $hotLead = Lead::factory()->create(['ai_score' => 85]);

        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-10'), ['lead_id' => $hotLead->id]);
        closedDeal($rep->id, DealStage::Lost, Carbon::parse('2026-07-11')); // no lead -> no_score

        $rows = $this->metrics->calculate(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));
        $row = collect($rows)->firstWhere('user_id', $rep->id);

        $hot = collect($row['by_score_band'])->firstWhere('dimension_value', 'hot');
        expect($hot['won_count'])->toBe(1);

        $noScore = collect($row['by_score_band'])->firstWhere('dimension_value', 'no_score');
        expect($noScore['label'])->toBe('No score data')->and($noScore['lost_count'])->toBe(1);
    });

    it('excludes deals closed outside the requested date range', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-06-15'));

        $rows = $this->metrics->calculate(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $row = collect($rows)->firstWhere('user_id', $rep->id);
        expect($row['overall']['won_count'])->toBe(0);
    });

    it('excludes deals that are still open', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        Deal::factory()->ownedBy($rep->id)->stage(DealStage::Negotiation)->create();

        $rows = $this->metrics->calculate(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));

        $row = collect($rows)->firstWhere('user_id', $rep->id);
        expect($row['overall']['won_count'])->toBe(0)->and($row['overall']['lost_count'])->toBe(0);
    });
});

describe('app:snapshot-rep-win-rates', function () {
    it('writes overall + source + score_band snapshot rows, idempotent on re-run', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        $lead = Lead::factory()->create(['source' => LeadSource::MetaAds, 'ai_score' => 85]);
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-10'), ['lead_id' => $lead->id]);

        $this->artisan('app:snapshot-rep-win-rates')->assertSuccessful();

        $overall = RepWinRateSnapshot::where('user_id', $rep->id)->whereDate('period_start', '2026-07-01')->where('dimension', 'overall')->first();
        expect($overall)->not->toBeNull()->and($overall->won_count)->toBe(1)->and($overall->dimension_value)->toBeNull();

        $source = RepWinRateSnapshot::where('user_id', $rep->id)->whereDate('period_start', '2026-07-01')->where('dimension', 'source')->first();
        expect($source->dimension_value)->toBe('meta_ads')->and($source->won_count)->toBe(1);

        // Re-run after another deal closes -- should update, not duplicate.
        closedDeal($rep->id, DealStage::Lost, Carbon::parse('2026-07-20'), ['lead_id' => $lead->id]);
        $this->artisan('app:snapshot-rep-win-rates')->assertSuccessful();

        expect(RepWinRateSnapshot::where('user_id', $rep->id)->whereDate('period_start', '2026-07-01')->where('dimension', 'overall')->count())->toBe(1);
        $updated = RepWinRateSnapshot::where('user_id', $rep->id)->whereDate('period_start', '2026-07-01')->where('dimension', 'overall')->first();
        expect($updated->won_count)->toBe(1)->and($updated->lost_count)->toBe(1);
    });

    it('respects a custom --month option', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-05-10'));

        $this->artisan('app:snapshot-rep-win-rates', ['--month' => '2026-05'])->assertSuccessful();

        expect(RepWinRateSnapshot::whereDate('period_start', '2026-05-01')->where('user_id', $rep->id)->exists())->toBeTrue();
        expect(RepWinRateSnapshot::whereDate('period_start', '2026-07-01')->exists())->toBeFalse();
    });

    it('is a no-op when disabled via config', function () {
        config(['services.reports.rep_win_rate_snapshot_enabled' => false]);
        $rep = User::factory()->role(UserRole::Sales)->create();
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-10'));

        $this->artisan('app:snapshot-rep-win-rates')->assertSuccessful();

        expect(RepWinRateSnapshot::count())->toBe(0);
    });
});

describe('reports/rep-win-rates route', function () {
    it('is reachable by Admin and Manager, reading only recorded snapshots', function () {
        RepWinRateSnapshot::create(['user_id' => User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte'])->id, 'period_start' => '2026-07-01', 'dimension' => 'overall', 'won_count' => 3, 'lost_count' => 1, 'win_rate' => 75]);

        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get(route('reports.rep-win-rates', ['month' => '2026-07']))
            ->assertOk()->assertSee('Kiran Katte')->assertSee('75%');

        $manager = User::factory()->role(UserRole::Manager)->create();
        $this->actingAs($manager)->get(route('reports.rep-win-rates'))->assertOk();
    });

    it('is forbidden for Sales', function () {
        $sales = User::factory()->role(UserRole::Sales)->create();
        $this->actingAs($sales)->get(route('reports.rep-win-rates'))->assertForbidden();
    });

    it('shows an empty state for a month with no snapshot yet, never computing live', function () {
        $rep = User::factory()->role(UserRole::Sales)->create();
        closedDeal($rep->id, DealStage::Won, Carbon::parse('2026-07-10')); // real closed deal, but never snapshotted

        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get(route('reports.rep-win-rates', ['month' => '2026-07']))
            ->assertOk()
            ->assertSee('No snapshot recorded for July 2026 yet');
    });

    it('exports a CSV with a header row', function () {
        RepWinRateSnapshot::create(['user_id' => User::factory()->role(UserRole::Sales)->create()->id, 'period_start' => '2026-07-01', 'dimension' => 'overall', 'won_count' => 2, 'lost_count' => 0, 'win_rate' => 100]);
        $admin = User::factory()->role(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('reports.rep-win-rates.export', ['month' => '2026-07']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        expect($response->streamedContent())->toContain('Win rate %');
    });
});
