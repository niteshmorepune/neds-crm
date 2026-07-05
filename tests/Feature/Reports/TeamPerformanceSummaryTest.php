<?php

use App\Enums\UserRole;
use App\Livewire\TeamPerformanceSummary;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

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
