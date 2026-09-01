<?php

use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Livewire\DealsBoard;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
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

it('signals the block when a deal is dropped on Lost with no reason', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    Livewire::actingAs($this->admin)
        ->test(DealsBoard::class)
        ->call('moveDeal', $deal->id, DealStage::Lost->value)
        ->assertDispatched('deal-move-blocked');

    expect($deal->fresh()->stage)->toBe(DealStage::Negotiation);
});

it('moves a deal to Lost from the board once a reason is picked', function () {
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();

    Livewire::actingAs($this->admin)
        ->test(DealsBoard::class)
        ->call('moveDeal', $deal->id, DealStage::Lost->value, 'price')
        ->assertNotDispatched('deal-move-blocked');

    $fresh = $deal->fresh();
    expect($fresh->stage)->toBe(DealStage::Lost)
        ->and($fresh->lost_reason)->toBe(DealLostReason::Price);
});

it('suggests a lost reason once a deal is dropped on the Lost column', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['reason' => 'went_dark', 'rationale' => 'No reply since the last call.'])]],
        'usage' => ['input_tokens' => 40, 'output_tokens' => 15],
    ])]);
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'They stopped replying after the proposal.']);

    Livewire::actingAs($this->admin)
        ->test(DealsBoard::class)
        ->call('suggestLostReason', $deal->id)
        ->assertSet('suggestedLostReason', 'went_dark')
        ->assertSet('lostReasonRationale', 'No reply since the last call.')
        ->assertSee('Suggested')
        ->assertSee('No reply since the last call.');
});

it('overriding the suggested lost reason persists the chosen value, not the suggestion', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['reason' => 'went_dark', 'rationale' => 'No reply since the last call.'])]],
        'usage' => ['input_tokens' => 40, 'output_tokens' => 15],
    ])]);
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'They stopped replying after the proposal.']);

    Livewire::actingAs($this->admin)
        ->test(DealsBoard::class)
        ->call('suggestLostReason', $deal->id)
        ->assertSet('suggestedLostReason', 'went_dark')
        ->call('moveDeal', $deal->id, DealStage::Lost->value, 'price');

    expect($deal->fresh()->lost_reason)->toBe(DealLostReason::Price);
});

it('does not suggest anything for an unauthorized deal', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherOwner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->ownedBy($otherOwner->id)->stage(DealStage::Negotiation)->create();

    Livewire::actingAs($sales)
        ->test(DealsBoard::class)
        ->call('suggestLostReason', $deal->id)
        ->assertSet('suggestedLostReason', null);

    Http::assertNothingSent();
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

it('shows inline hints clarifying the Stage and Service fields on the deal detail page', function () {
    $deal = Deal::factory()->stage(DealStage::New)->create();
    $this->seed(MenuItemsSeeder::class);

    $this->actingAs($this->admin)->get(route('deals.show', $deal))->assertOk()
        ->assertSee('Move to Negotiation only once a quotation has actually been sent')
        ->assertSee("don't reach for a vague catch-all", false);
});

it('shows an inline hint on the Add deal form service field', function () {
    $this->seed(MenuItemsSeeder::class);

    Livewire::actingAs($this->admin)->test(DealsBoard::class)
        ->set('showAddForm', true)
        ->assertSee('Covers two services? Pick the main one and name the other in the title.');
});

it('shows similar closed deals on the deal detail page, or an empty-state message', function () {
    $this->seed(MenuItemsSeeder::class);
    $service = Service::factory()->create();

    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $service->id, 'value' => 150000]);

    $this->actingAs($this->admin)->get(route('deals.show', $deal))->assertOk()
        ->assertSee('Deals like this one')
        ->assertSee('No similar closed deals yet for this service.');

    $similarCustomer = Customer::factory()->create(['company_name' => 'Similar Client Co']);
    Deal::factory()->stage(DealStage::Won)->create([
        'service_id' => $service->id, 'value' => 140000, 'customer_id' => $similarCustomer->id,
    ]);

    $this->actingAs($this->admin)->get(route('deals.show', $deal))->assertOk()
        ->assertSee('Similar Client Co')
        ->assertDontSee('No similar closed deals yet for this service.');
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

it('flags a deal as stale after more than 10 days in its current stage', function () {
    $deal = Deal::factory()->create();
    $deal->forceFill(['stage_changed_at' => now()->subDays(14)])->saveQuietly();

    Livewire::actingAs($this->admin)->test(DealsBoard::class)
        ->assertSee('⚠')
        ->assertSee('14 days in stage');
});

it('does not flag a deal as stale within 10 days of its current stage', function () {
    Deal::factory()->create();

    Livewire::actingAs($this->admin)->test(DealsBoard::class)
        ->assertSee('0 days in stage')
        ->assertDontSee('⚠');
});
