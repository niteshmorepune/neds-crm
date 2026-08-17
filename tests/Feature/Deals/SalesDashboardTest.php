<?php

use App\Enums\DealStage;
use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\SalesTarget;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('lets admin and sales view the sales dashboard but forbids support', function () {
    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('sales-dashboard.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Support)->create())->get(route('sales-dashboard.index'))->assertForbidden();
});

it('shows the rep leaderboard and target form to admin/manager only', function () {
    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSee('Rep leaderboard')
        ->assertSee('Save targets');

    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('sales-dashboard.index'))
        ->assertDontSee('Rep leaderboard')
        ->assertDontSee('Save targets');
});

it('lists needs-attention deals in their correct categories', function () {
    $stale = Deal::factory()->create(['title' => 'Stale Deal', 'value' => 100000]);
    $stale->forceFill(['stage_changed_at' => now()->subDays(14)])->saveQuietly();

    Deal::factory()->create([
        'title' => 'Overdue Followup Deal',
        'value' => 100000,
        'next_follow_up_at' => now()->subDay(),
    ]);

    Deal::factory()->create(['title' => 'Unowned Deal', 'value' => 100000, 'owner_id' => null]);

    Deal::factory()->create(['title' => 'Zero Value Deal', 'value' => 0]);

    $response = $this->actingAs($this->admin)->get(route('sales-dashboard.index'));

    $response->assertSee('Stale Deal')
        ->assertSee('Overdue Followup Deal')
        ->assertSee('Unowned Deal')
        ->assertSee('Zero Value Deal');
});

it('shows "no target set" until a company target exists, then shows progress', function () {
    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSee('No target set');

    Deal::factory()->stage(DealStage::Won)->create([
        'value' => 500000,
        'won_at' => now(),
    ]);

    SalesTarget::factory()->create([
        'user_id' => null,
        'period_type' => TargetPeriodType::Month,
        'period_start' => TargetPeriodType::Month->currentPeriodStart(),
        'target_value' => 1000000,
    ]);

    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSee('50%'); // 5,000 won of 10,000 target
});

it('shows a data-suggested target next to a blank field but not once a target is already set', function () {
    $rep = User::factory()->role(UserRole::Sales)->create();
    $monthAgo1 = now()->copy()->subMonthsNoOverflow(1)->startOfMonth()->addDays(10);
    $monthAgo2 = now()->copy()->subMonthsNoOverflow(2)->startOfMonth()->addDays(10);

    Deal::factory()->stage(DealStage::Won)->ownedBy($rep->id)->create(['value' => 6000000, 'won_at' => $monthAgo1]);
    Deal::factory()->stage(DealStage::Won)->ownedBy($rep->id)->create(['value' => 6000000, 'won_at' => $monthAgo2]);

    // Rep: (6,000,000+6,000,000+0)/3 = 4,000,000; +10% = 4,400,000 paise = ₹44,000.
    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSee('Suggested: ₹44,000.00');

    // Set targets on BOTH fields the suggestion could be attached to (the
    // company total equals the rep total here, since it's the only rep with
    // deals) — otherwise the still-blank company field would keep showing
    // the identical suggested figure and this assertion would pass for the
    // wrong reason.
    SalesTarget::factory()->create([
        'user_id' => $rep->id,
        'period_type' => TargetPeriodType::Month,
        'period_start' => TargetPeriodType::Month->currentPeriodStart(),
        'target_value' => 5000000,
    ]);
    SalesTarget::factory()->create([
        'user_id' => null,
        'period_type' => TargetPeriodType::Month,
        'period_start' => TargetPeriodType::Month->currentPeriodStart(),
        'target_value' => 5000000,
    ]);

    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertDontSee('Suggested: ₹44,000.00');
});

it('shows no suggested-target hint when the trailing 3 months lack enough data', function () {
    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertDontSee('Suggested:');
});

/**
 * The KPI strip and stage-conversion row used to also render on the
 * Sales Pipeline board (duplicating this exact content) — moved here
 * as the single source of truth, see the DealsBoard milestone that
 * trimmed the board down to just the drag-and-drop columns.
 */
it('shows KPI strip figures for the sales pipeline', function () {
    $now = now();

    // Open deals: New (10% probability) and Proposal (50% probability).
    Deal::factory()->stage(DealStage::New)->create(['value' => 100000]);
    Deal::factory()->stage(DealStage::Proposal)->create(['value' => 200000]);

    // Won deal: 10-day cycle, closed just now (counts for this month/FY, win rate, avg deal size).
    Deal::factory()->stage(DealStage::Won)->create([
        'value' => 500000,
        'created_at' => $now->copy()->subDays(10),
        'won_at' => $now,
    ]);

    // Lost deal: excluded from pipeline/forecast, counts toward the win-rate denominator.
    Deal::factory()->stage(DealStage::Lost)->create(['value' => 300000]);

    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSee('₹3,000.00') // open pipeline: 1,000 (New) + 2,000 (Proposal)
        ->assertSee('₹1,100.00') // weighted forecast: 10%*1,000 + 50%*2,000
        ->assertSee('₹5,000.00') // won this month / this FY / avg deal size (single Won deal)
        ->assertSee('50%') // win rate: 1 won / (1 won + 1 lost)
        ->assertSee('10 days'); // avg sales cycle: created_at -> won_at
});

it('shows an em dash for KPIs with no closed deals yet', function () {
    Deal::factory()->stage(DealStage::New)->create(['value' => 100000]);

    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSeeInOrder(['Win rate', '—'])
        ->assertSeeInOrder(['Avg deal size', '—'])
        ->assertSeeInOrder(['Avg sales cycle', '—']);
});

it('shows a stage conversion percentage once enough deals have moved through', function () {
    // 5 deals enter New (via creation); 3 of them advance to Contacted.
    $deals = Deal::factory()->count(5)->create();
    $deals->take(3)->each(fn (Deal $deal) => $deal->moveToStage(DealStage::Contacted));

    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSee('60%'); // 3 of 5 New deals advanced to Contacted
});

it('shows "not enough data yet" for a stage pair below the minimum sample', function () {
    Deal::factory()->count(2)->create();

    $this->actingAs($this->admin)->get(route('sales-dashboard.index'))
        ->assertSee('Not enough data yet');
});

it('no longer shows the KPI strip or stage conversion on the pipeline board itself', function () {
    Deal::factory()->stage(DealStage::Won)->create(['value' => 500000, 'won_at' => now()]);

    $this->actingAs($this->admin)->get(route('deals.index'))
        ->assertOk()
        ->assertDontSee('Weighted forecast')
        ->assertDontSee('Avg sales cycle')
        ->assertSee('Sales Dashboard');
});
