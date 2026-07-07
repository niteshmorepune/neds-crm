<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Livewire\DealsBoard;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('renders a distinct color per deal stage column', function () {
    Livewire::actingAs($this->admin)->test(DealsBoard::class)
        ->assertSee('border-t-slate-400', false)
        ->assertSee('border-t-blue-400', false)
        ->assertSee('border-t-purple-400', false)
        ->assertSee('border-t-amber-400', false)
        ->assertSee('border-t-green-400', false)
        ->assertSee('border-t-red-400', false);
});

it('moves a deal to a new stage from the board', function () {
    $deal = Deal::factory()->stage(DealStage::New)->create();

    Livewire::actingAs($this->admin)
        ->test(DealsBoard::class)
        ->call('moveDeal', $deal->id, DealStage::Proposal->value);

    expect($deal->fresh()->stage)->toBe(DealStage::Proposal);
});

it('does not move a terminal deal and signals the block', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();

    Livewire::actingAs($this->admin)
        ->test(DealsBoard::class)
        ->call('moveDeal', $deal->id, DealStage::New->value)
        ->assertDispatched('deal-move-blocked');

    expect($deal->fresh()->stage)->toBe(DealStage::Won);
});

it('creates a deal from the board', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(DealsBoard::class)
        ->set('customer_id', $customer->id)
        ->set('title', 'Website revamp')
        ->set('value', '20000')
        ->call('createDeal')
        ->assertHasNoErrors();

    $deal = Deal::firstWhere('title', 'Website revamp');
    expect($deal)->not->toBeNull()
        ->and($deal->stage)->toBe(DealStage::New)
        ->and($deal->value)->toBe(2000000); // 20000 rupees -> paise
});

it('renders the deal detail page', function () {
    $deal = Deal::factory()->create();
    $this->seed(MenuItemsSeeder::class);

    $this->actingAs($this->admin)->get(route('deals.show', $deal))->assertOk()->assertSee($deal->title);
});
