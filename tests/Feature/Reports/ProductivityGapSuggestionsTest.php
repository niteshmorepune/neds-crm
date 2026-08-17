<?php

use App\Enums\TargetMetric;
use App\Enums\UserRole;
use App\Livewire\ProductivityGapSuggestions;
use App\Models\Lead;
use App\Models\RoleTarget;
use App\Models\User;
use App\Services\ReportMetrics;
use App\Services\RoleTargetMetrics;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function rankedRowsForTwoSalesReps(): array
{
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    Lead::factory()->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    return app(ReportMetrics::class)->rankedEmployeePerformance(now()->startOfMonth(), now()->endOfMonth())->all();
}

/**
 * Mirrors ReportController::employeePerformance()'s own enrichment (rows
 * from rankedEmployeePerformance() + a 'target' key per row) — the
 * controller does this before ever handing rows to this Livewire component,
 * so a test exercising the component directly must do the same.
 */
function withTargetProgress(array $rows): array
{
    $metrics = app(RoleTargetMetrics::class);

    return collect($rows)->map(function (array $row) use ($metrics) {
        $row['target'] = $metrics->progressForUser(User::find($row['user_id']));

        return $row;
    })->all();
}

it('lets an admin generate team productivity suggestions', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $rows = rankedRowsForTwoSalesReps();
    $bobId = collect($rows)->firstWhere('user', 'Bob')['user_id'];

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                ['id' => $bobId, 'suggestion' => 'Make a few more calls this week to boost conversions.'],
            ])]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 20],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    $component = Livewire::actingAs($admin)
        ->test(ProductivityGapSuggestions::class, ['rows' => $rows])
        ->call('generate');

    expect($component->get('suggestions'))->toBe([$bobId => 'Make a few more calls this week to boost conversions.']);
});

it('ignores a suggestion for a user_id that was never in the ranked rows', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $rows = rankedRowsForTwoSalesReps();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                ['id' => 999999, 'suggestion' => 'Should never appear.'],
            ])]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 20],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(ProductivityGapSuggestions::class, ['rows' => $rows])
        ->call('generate')
        ->assertSet('suggestions', []);
});

it('forbids a non-admin/manager from generating team suggestions', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $rows = rankedRowsForTwoSalesReps();
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(ProductivityGapSuggestions::class, ['rows' => $rows])
        ->call('generate')
        ->assertForbidden();
});

it('hides the suggest button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $rows = rankedRowsForTwoSalesReps();
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(ProductivityGapSuggestions::class, ['rows' => $rows])
        ->assertDontSee('Suggest Improvements for the Team');
});

it('includes a person\'s target progress in the batched AI prompt when one is set', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $support = User::factory()->role(UserRole::Support)->create(['name' => 'Priya']);
    User::factory()->role(UserRole::Support)->create(['name' => 'Rohit']); // peer
    RoleTarget::factory()->forUser($support->id, TargetMetric::TicketsResolved)->create(['target_value' => 10]);
    $rows = app(ReportMetrics::class)->rankedEmployeePerformance(now()->startOfMonth(), now()->endOfMonth())->all();
    $rows = withTargetProgress($rows);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([])]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 20],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)->test(ProductivityGapSuggestions::class, ['rows' => $rows])->call('generate');

    Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Priya')
        && str_contains($request['messages'][0]['content'], 'monthly target — Tickets resolved: 0 of 10'));
});

it('renders score and rank for a ranked role group', function () {
    $rows = rankedRowsForTwoSalesReps();
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test(ProductivityGapSuggestions::class, ['rows' => $rows])
        ->assertSee('of 2');
});
