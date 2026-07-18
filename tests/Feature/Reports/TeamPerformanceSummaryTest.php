<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Livewire\TeamPerformanceSummary;
use App\Models\Deal;
use App\Models\DealStageTransition;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * 3 completed dwell periods in Contacted for $rep, backdated so the dwell
 * is exactly $days each — meets SalesPipelineMetrics::MIN_DWELL_SAMPLE (3)
 * for both the rep and the (identical, single-rep) team average here.
 */
function seedContactedDwell(User $rep, int $days): void
{
    for ($i = 0; $i < 3; $i++) {
        $deal = Deal::factory()->create(['stage' => DealStage::Contacted, 'owner_id' => $rep->id]);
        $entered = DealStageTransition::where('deal_id', $deal->id)->firstOrFail();
        $entered->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

        $left = DealStageTransition::create(['deal_id' => $deal->id, 'from_stage' => DealStage::Contacted->value, 'to_stage' => DealStage::Negotiation->value]);
        $left->forceFill(['created_at' => now()->subDays(30 - $days)])->saveQuietly();
    }
}

it('lets an admin generate and dismiss a team performance summary', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '- The team is on track this month.']],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(TeamPerformanceSummary::class, ['fromDate' => now()->startOfMonth()->toDateString(), 'toDate' => now()->endOfMonth()->toDateString()])
        ->call('generate')
        ->assertSet('summary', '- The team is on track this month.')
        ->call('dismiss')
        ->assertSet('summary', null);
});

it('feeds real per-rep stage-dwell figures into the AI prompt when enough data exists', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '- Priya stalls longest in Negotiation... wait Contacted.']],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $priya = User::factory()->role(UserRole::Sales)->create(['name' => 'Priya']);
    seedContactedDwell($priya, 18);

    Livewire::actingAs($admin)
        ->test(TeamPerformanceSummary::class, ['fromDate' => now()->startOfMonth()->toDateString(), 'toDate' => now()->endOfMonth()->toDateString()])
        ->call('generate');

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'Priya') && str_contains($body, 'Contacted stage') && str_contains($body, '18');
    });
});

it('forbids a non-manager from generating a summary', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(TeamPerformanceSummary::class, ['fromDate' => now()->startOfMonth()->toDateString(), 'toDate' => now()->endOfMonth()->toDateString()])
        ->call('generate')
        ->assertForbidden();
});

it('hides the generate button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(TeamPerformanceSummary::class, ['fromDate' => now()->startOfMonth()->toDateString(), 'toDate' => now()->endOfMonth()->toDateString()])
        ->assertDontSee('Generate AI Summary');
});
