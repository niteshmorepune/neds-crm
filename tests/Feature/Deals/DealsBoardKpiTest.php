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

it('shows KPI strip figures for the sales pipeline', function () {
    $now = now();

    // Open deals: New (10% probability) and Proposal (50% probability).
    Deal::factory()->stage(DealStage::New)->create(['value' => 100000]);
    Deal::factory()->stage(DealStage::Proposal)->create(['value' => 200000]);

    // Won deal: 10-day cycle, closed just now (counts for this month/FY, win rate, avg deal size).
    Deal::factory()->stage(DealStage::Won)->create([
        'value' => 500000,
        'created_at' => $now->copy()->subDays(10),
        'won_at' => $now,
    ]);

    // Lost deal: excluded from pipeline/forecast, counts toward the win-rate denominator.
    Deal::factory()->stage(DealStage::Lost)->create(['value' => 300000]);

    Livewire::actingAs($this->admin)->test(DealsBoard::class)
        ->assertSee('₹3,000.00') // open pipeline: 1,000 (New) + 2,000 (Proposal)
        ->assertSee('₹1,100.00') // weighted forecast: 10%*1,000 + 50%*2,000
        ->assertSee('₹5,000.00') // won this month / this FY / avg deal size (single Won deal)
        ->assertSee('50%') // win rate: 1 won / (1 won + 1 lost)
        ->assertSee('10 days'); // avg sales cycle: created_at -> won_at
});

it('shows an em dash for KPIs with no closed deals yet', function () {
    Deal::factory()->stage(DealStage::New)->create(['value' => 100000]);

    Livewire::actingAs($this->admin)->test(DealsBoard::class)
        ->assertSeeInOrder(['Win rate', '—'])
        ->assertSeeInOrder(['Avg deal size', '—'])
        ->assertSeeInOrder(['Avg sales cycle', '—']);
});

it('requires a value when creating a deal from the board', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->admin)->test(DealsBoard::class)
        ->set('customer_id', $customer->id)
        ->set('title', 'No value deal')
        ->set('value', '')
        ->call('createDeal')
        ->assertHasErrors(['value' => 'required']);
});

it('requires a value when updating a deal', function () {
    $deal = Deal::factory()->create();
    $this->seed(MenuItemsSeeder::class);

    $this->actingAs($this->admin)
        ->put(route('deals.update', $deal), [
            'title' => $deal->title,
            'stage' => $deal->stage->value,
        ])
        ->assertSessionHasErrors('value');
});
