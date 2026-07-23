<?php

use App\Enums\UserRole;
use App\Models\AiUsage;
use App\Models\AiUsageSetting;
use App\Models\User;
use App\Services\AiUsageMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(AiUsageMetrics::class);

    config([
        'services.anthropic.pricing' => [
            'claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00],
            'default' => ['input' => 1.00, 'output' => 5.00],
        ],
        'services.anthropic.usd_to_inr' => 87.0,
    ]);
});

it('groups calls by feature with call count and token totals', function () {
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'model' => 'claude-haiku-4-5-20251001', 'input_tokens' => 100, 'output_tokens' => 50, 'created_at' => now()]);
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'model' => 'claude-haiku-4-5-20251001', 'input_tokens' => 200, 'output_tokens' => 80, 'created_at' => now()]);
    AiUsage::factory()->create(['feature' => 'draft_ticket_reply', 'model' => 'claude-haiku-4-5-20251001', 'input_tokens' => 500, 'output_tokens' => 300, 'created_at' => now()]);

    $data = $this->metrics->monthly(now()->startOfMonth(), now()->endOfMonth());

    expect($data['total_calls'])->toBe(3)
        ->and($data['by_feature'])->toHaveCount(2);

    $leadScoring = collect($data['by_feature'])->firstWhere('feature', 'lead_scoring');
    expect($leadScoring['calls'])->toBe(2)
        ->and($leadScoring['input_tokens'])->toBe(300)
        ->and($leadScoring['output_tokens'])->toBe(130)
        ->and($leadScoring['label'])->toBe('Lead Scoring');
});

it('estimates cost from the configured per-model rate and USD to INR conversion', function () {
    // 1,000,000 input tokens @ $1.00/MTok + 1,000,000 output tokens @ $5.00/MTok
    // = $6.00 -> at 87 INR/USD -> ₹522.00 -> 52200 paise.
    AiUsage::factory()->create([
        'feature' => 'lead_scoring',
        'model' => 'claude-haiku-4-5-20251001',
        'input_tokens' => 1_000_000,
        'output_tokens' => 1_000_000,
        'created_at' => now(),
    ]);

    $data = $this->metrics->monthly(now()->startOfMonth(), now()->endOfMonth());

    expect($data['estimated_cost_paise'])->toBe(52200);
});

it('falls back to the default rate for an unrecognised model', function () {
    AiUsage::factory()->create([
        'feature' => 'lead_scoring',
        'model' => 'some-future-model',
        'input_tokens' => 1_000_000,
        'output_tokens' => 0,
        'created_at' => now(),
    ]);

    $data = $this->metrics->monthly(now()->startOfMonth(), now()->endOfMonth());

    // 1,000,000 input tokens @ default $1.00/MTok -> $1.00 -> ₹87.00 -> 8700 paise.
    expect($data['estimated_cost_paise'])->toBe(8700);
});

it('tallies helpful/not-helpful feedback per feature and in total', function () {
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'feedback' => 'up', 'created_at' => now()]);
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'feedback' => 'up', 'created_at' => now()]);
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'feedback' => 'down', 'created_at' => now()]);
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'feedback' => null, 'created_at' => now()]);
    AiUsage::factory()->create(['feature' => 'draft_ticket_reply', 'feedback' => 'down', 'created_at' => now()]);

    $data = $this->metrics->monthly(now()->startOfMonth(), now()->endOfMonth());

    $leadScoring = collect($data['by_feature'])->firstWhere('feature', 'lead_scoring');
    expect($leadScoring['feedback_up'])->toBe(2)
        ->and($leadScoring['feedback_down'])->toBe(1);

    $reply = collect($data['by_feature'])->firstWhere('feature', 'draft_ticket_reply');
    expect($reply['feedback_up'])->toBe(0)
        ->and($reply['feedback_down'])->toBe(1);

    expect($data['total_feedback_up'])->toBe(2)
        ->and($data['total_feedback_down'])->toBe(2);
});

it('reports zero feedback for a feature nobody has rated', function () {
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'created_at' => now()]);

    $data = $this->metrics->monthly(now()->startOfMonth(), now()->endOfMonth());

    expect($data['by_feature'][0]['feedback_up'])->toBe(0)
        ->and($data['by_feature'][0]['feedback_down'])->toBe(0)
        ->and($data['total_feedback_up'])->toBe(0)
        ->and($data['total_feedback_down'])->toBe(0);
});

it('excludes usage rows outside the requested period', function () {
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'created_at' => now()->subMonths(2)]);

    $data = $this->metrics->monthly(now()->startOfMonth(), now()->endOfMonth());

    expect($data['total_calls'])->toBe(0)
        ->and($data['by_feature'])->toBe([]);
});

it('renders the AI usage report page', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'created_at' => now()]);

    $this->actingAs($manager)
        ->get(route('reports.ai-usage'))
        ->assertOk()
        ->assertSee('AI Usage Report')
        ->assertSee('Lead Scoring');
});

it('lets admin and manager view the report but forbids a sales rep', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->get(route('reports.ai-usage'))->assertOk();
    $this->actingAs($manager)->get(route('reports.ai-usage'))->assertOk();
    $this->actingAs($sales)->get(route('reports.ai-usage'))->assertForbidden();
});

it('exports the report as CSV, including the feedback tally', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'feedback' => 'up', 'created_at' => now()]);

    $response = $this->actingAs($manager)->get(route('reports.ai-usage.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Helpful')->toContain('Not helpful');
});

it('shows the feedback tally on the report page once a draft has been rated', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    AiUsage::factory()->create(['feature' => 'lead_scoring', 'feedback' => 'up', 'created_at' => now()]);

    $this->actingAs($manager)
        ->get(route('reports.ai-usage'))
        ->assertOk()
        ->assertSee('Feedback given')
        ->assertSee('helpful / not helpful clicks');
});

it('fetches Drishti AI usage totals via the service-key endpoint and converts USD to paise', function () {
    config([
        'services.drishti.base_url' => 'https://nedsdrishti.in',
        'services.drishti.service_key' => 'drishti-secret',
        'services.anthropic.usd_to_inr' => 87.0,
    ]);
    Http::fake([
        'nedsdrishti.in/api/ai/usage*' => Http::response([
            'data' => ['totals' => ['_sum' => ['inputTokens' => 1000, 'outputTokens' => 500, 'costUsd' => 2.5], '_count' => 7]],
        ]),
    ]);

    $result = app(AiUsageMetrics::class)->drishtiUsage(now()->startOfMonth(), now()->endOfMonth());

    expect($result)->toBe([
        'calls' => 7,
        'input_tokens' => 1000,
        'output_tokens' => 500,
        'estimated_cost_paise' => (int) round(2.5 * 87.0 * 100),
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'nedsdrishti.in/api/ai/usage')
        && $request->hasHeader('X-Service-Key', 'drishti-secret'));
});

it('returns null Drishti usage gracefully when the call fails, is unconfigured, or throws', function () {
    config(['services.drishti.base_url' => null, 'services.drishti.service_key' => null]);
    expect(app(AiUsageMetrics::class)->drishtiUsage(now()->startOfMonth(), now()->endOfMonth()))->toBeNull();

    config(['services.drishti.base_url' => 'https://nedsdrishti.in', 'services.drishti.service_key' => 'drishti-secret']);
    Http::fake(['nedsdrishti.in/*' => Http::response('boom', 500)]);
    expect(app(AiUsageMetrics::class)->drishtiUsage(now()->startOfMonth(), now()->endOfMonth()))->toBeNull();
});

it('shows the AI usage report page even when Drishti is unreachable', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    config(['services.drishti.base_url' => 'https://nedsdrishti.in', 'services.drishti.service_key' => 'drishti-secret']);
    Http::fake(['nedsdrishti.in/*' => Http::response('boom', 500)]);

    $this->actingAs($manager)
        ->get(route('reports.ai-usage'))
        ->assertOk()
        ->assertSee('Unavailable', false);
});

it('computes budget percentage from combined CRM + Drishti cost against the configured ceiling', function () {
    AiUsageSetting::current()->update(['monthly_budget_paise' => 10000]);

    $status = app(AiUsageMetrics::class)->budgetStatus(4000, 2000);

    expect($status)->toBe(['combined_cost_paise' => 6000, 'budget_paise' => 10000, 'pct' => 60]);
});

it('reports a null percentage when no budget is configured yet', function () {
    $status = app(AiUsageMetrics::class)->budgetStatus(4000, null);

    expect($status['pct'])->toBeNull()
        ->and($status['combined_cost_paise'])->toBe(4000);
});

it('lets a manager update the monthly AI budget', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)
        ->post(route('reports.ai-usage.settings.update'), ['monthly_budget' => 5000])
        ->assertRedirect();

    expect(AiUsageSetting::current()->monthly_budget_paise)->toBe(5000 * 100);
});

it('blocks a sales rep from updating the monthly AI budget', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)
        ->post(route('reports.ai-usage.settings.update'), ['monthly_budget' => 5000])
        ->assertForbidden();
});
