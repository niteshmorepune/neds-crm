<?php

use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Livewire\DealLostReasonField;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('suggests a reason when the deal-stage-set-to-lost event fires', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['reason' => 'not_a_fit', 'rationale' => 'Notes say the scope never matched.'])]],
        'usage' => ['input_tokens' => 30, 'output_tokens' => 10],
    ])]);
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'This was never really the right fit for them.']);

    Livewire::actingAs($admin)
        ->test(DealLostReasonField::class, ['deal' => $deal])
        ->dispatch('deal-stage-set-to-lost')
        ->assertSet('suggestedReason', 'not_a_fit')
        ->assertSet('rationale', 'Notes say the scope never matched.')
        ->assertSee('suggested')
        ->assertSee('Notes say the scope never matched.');

    expect($deal->fresh()->ai_suggested_lost_reason)->toBe(DealLostReason::NotAFit);
});

it('does not call Claude a second time if the event fires twice', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['reason' => 'price', 'rationale' => 'Pricing came up repeatedly.'])]],
        'usage' => ['input_tokens' => 30, 'output_tokens' => 10],
    ])]);
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'They said it was too expensive.']);

    Livewire::actingAs($admin)
        ->test(DealLostReasonField::class, ['deal' => $deal])
        ->dispatch('deal-stage-set-to-lost')
        ->dispatch('deal-stage-set-to-lost');

    Http::assertSentCount(1);
});

it('shows no pre-selection and a helpful note when the deal has no history', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake();
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(); // no notes, no lead

    Livewire::actingAs($admin)
        ->test(DealLostReasonField::class, ['deal' => $deal])
        ->dispatch('deal-stage-set-to-lost')
        ->assertSet('suggestedReason', null)
        ->assertSee('No suggestion available');

    Http::assertNothingSent();
    expect($deal->fresh()->ai_suggested_lost_reason)->toBeNull();
});

it('forbids suggesting for a deal the viewer cannot update', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherOwner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->ownedBy($otherOwner->id)->stage(DealStage::Negotiation)->create();

    Livewire::actingAs($sales)
        ->test(DealLostReasonField::class, ['deal' => $deal])
        ->dispatch('deal-stage-set-to-lost')
        ->assertForbidden();
});
