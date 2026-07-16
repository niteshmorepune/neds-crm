<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('moves a deal through non-terminal stages', function () {
    $deal = Deal::factory()->stage(DealStage::New)->create();

    expect($deal->moveToStage(DealStage::Proposal))->toBeTrue()
        ->and($deal->fresh()->stage)->toBe(DealStage::Proposal);
});

it('refuses to move a won or lost deal to another stage', function (DealStage $terminal) {
    $deal = Deal::factory()->stage($terminal)->create();

    expect($deal->moveToStage(DealStage::Negotiation))->toBeFalse()
        ->and($deal->fresh()->stage)->toBe($terminal);
})->with([
    'won' => DealStage::Won,
    'lost' => DealStage::Lost,
]);

it('updates a deal stage via the controller', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Won->value,
            'value' => 1000,
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->stage)->toBe(DealStage::Won)
        ->and($deal->fresh()->value)->toBe(100000) // 1000 rupees -> paise
        ->and($deal->fresh()->won_at)->not->toBeNull();
});

it('stamps won_at when deal moves to Won and clears it if stage reverts', function () {
    $deal = Deal::factory()->stage(DealStage::New)->create();
    expect($deal->won_at)->toBeNull();

    $deal->update(['stage' => DealStage::Won]);
    expect($deal->fresh()->won_at)->not->toBeNull();

    // Directly force a revert (bypassing moveToStage terminal guard) to confirm clearing.
    // Must use save() (not saveQuietly) so the saving hook fires.
    $deal->stage = DealStage::Negotiation;
    $deal->save();
    expect($deal->fresh()->won_at)->toBeNull();
});

it('blocks a stage change on a terminal deal via the controller', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Negotiation->value,
            'value' => 1000,
        ])
        ->assertSessionHasErrors('stage');

    expect($deal->fresh()->stage)->toBe(DealStage::Won);
});
