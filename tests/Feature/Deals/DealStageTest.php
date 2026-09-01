<?php

use App\Enums\DealLostReason;
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

it('allows updating the value of a Won deal as long as stage is resubmitted unchanged', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create(['value' => 100000]);

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Won->value,
            'value' => 5000,
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->stage)->toBe(DealStage::Won)
        ->and($deal->fresh()->value)->toBe(500000);
});

it('saves the next follow-up time in IST, not UTC', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Contacted->value,
            'value' => 1000,
            'next_follow_up_at' => '2026-08-01T13:10',
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->next_follow_up_at->toIso8601String())->toBe('2026-08-01T07:40:00+00:00');
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

it('refuses to move a deal to Lost without a reason', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    expect($deal->moveToStage(DealStage::Lost))->toBeFalse()
        ->and($deal->fresh()->stage)->toBe(DealStage::Negotiation)
        ->and($deal->fresh()->lost_reason)->toBeNull();
});

it('moves a deal to Lost and persists the reason', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    expect($deal->moveToStage(DealStage::Lost, DealLostReason::Competitor))->toBeTrue();

    $fresh = $deal->fresh();
    expect($fresh->stage)->toBe(DealStage::Lost)
        ->and($fresh->lost_reason)->toBe(DealLostReason::Competitor);
});

it('requires a lost_reason via the deal edit form when moving to Lost', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Lost->value,
            'value' => 1000,
        ])
        ->assertSessionHasErrors('lost_reason');

    expect($deal->fresh()->stage)->toBe(DealStage::Negotiation);
});

it('moves a deal to Lost via the edit form when a reason is provided', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Lost->value,
            'lost_reason' => 'went_dark',
            'value' => 1000,
        ])
        ->assertRedirect(route('deals.show', $deal));

    $fresh = $deal->fresh();
    expect($fresh->stage)->toBe(DealStage::Lost)
        ->and($fresh->lost_reason)->toBe(DealLostReason::WentDark);
});
