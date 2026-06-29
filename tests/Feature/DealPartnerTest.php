<?php

use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('deal show page renders with referred-by field', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $deal = Deal::factory()->create(['owner_id' => $user->id]);

    actingAs($user)
        ->get(route('deals.show', $deal))
        ->assertOk()
        ->assertSee('Referred by');
});

it('manager can set a partner on a deal', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $deal = Deal::factory()->create(['owner_id' => $user->id]);
    $partner = Partner::factory()->create();

    actingAs($user)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => $deal->stage->value,
            'value' => '10000',
            'partner_id' => $partner->id,
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->partner_id)->toBe($partner->id);
});

it('manager can clear a partner from a deal', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $partner = Partner::factory()->create();
    $deal = Deal::factory()->create(['owner_id' => $user->id, 'partner_id' => $partner->id]);

    actingAs($user)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => $deal->stage->value,
            'value' => '10000',
            'partner_id' => '',
        ])
        ->assertRedirect(route('deals.show', $deal));

    expect($deal->fresh()->partner_id)->toBeNull();
});

it('deal partner_id is nulled when partner is deleted', function () {
    $partner = Partner::factory()->create();
    $deal = Deal::factory()->create(['partner_id' => $partner->id]);

    $partner->delete();

    expect($deal->fresh()->partner_id)->toBeNull();
});
