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

it('sets a deal\'s confidence via the controller', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Negotiation->value,
            'value' => 1000,
            'confidence' => 7,
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->confidence)->toBe(7);
});

it('leaves confidence unset when the field is left blank', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Negotiation->value,
            'value' => 1000,
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->confidence)->toBeNull();
});

it('clears a previously-set confidence when the field is resubmitted blank', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(['confidence' => 8]);

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Negotiation->value,
            'value' => 1000,
            'confidence' => '',
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->confidence)->toBeNull();
});

it('rejects a confidence value outside 1-10', function (int $invalid) {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => DealStage::Negotiation->value,
            'value' => 1000,
            'confidence' => $invalid,
        ])
        ->assertSessionHasErrors('confidence');

    expect($deal->fresh()->confidence)->toBeNull();
})->with([0, 11, -1]);

it('shows the confidence badge on the deal detail page once set', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(['confidence' => 9]);

    $this->actingAs($this->admin)
        ->get(route('deals.show', $deal))
        ->assertSee('9/10');
});

it('shows "Not set" on the deal detail page when confidence is null', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(['confidence' => null]);

    $this->actingAs($this->admin)
        ->get(route('deals.show', $deal))
        ->assertSee('Not set');
});

it('shows the confidence badge on the Kanban board card once set', function () {
    Deal::factory()->stage(DealStage::Negotiation)->create(['confidence' => 3, 'title' => 'A confident deal']);

    $this->actingAs($this->admin)
        ->get(route('deals.index'))
        ->assertSee('A confident deal')
        ->assertSee('3/10');
});

it('does not show a confidence badge on the Kanban board card when unset', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(['confidence' => null, 'title' => 'An unscored deal']);

    $this->actingAs($this->admin)
        ->get(route('deals.index'))
        ->assertSee('An unscored deal')
        ->assertDontSee('/10');
});
