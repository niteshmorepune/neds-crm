<?php

use App\Enums\UserRole;
use App\Livewire\CallPriorityList;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function callPriorityRow(int $customerId, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customerId,
        'company_name' => 'Test Co',
        'days_since_contact' => 10,
        'follow_up_due' => true,
        'top_stage_label' => 'Negotiation',
        'top_stage_probability' => 75,
        'score' => 88.0,
        'reason' => 'No contact in 10 day(s) · Follow-up due · Deal in Negotiation (75%)',
    ], $overrides);
}

it('lets a sales rep generate and rate a talking point for their own client', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Ask about the pending proposal.']],
            'usage' => ['input_tokens' => 15, 'output_tokens' => 8],
        ]),
    ]);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->ownedBy($sales->id)->create();

    $component = Livewire::actingAs($sales)
        ->test(CallPriorityList::class, ['rows' => [callPriorityRow($customer->id)]])
        ->call('suggestTalkingPoint', $customer->id);

    expect($component->get('suggestions'))->toBe([$customer->id => 'Ask about the pending proposal.']);

    $component->call('rate', $customer->id, 'up');
    expect($component->get('feedback'))->toBe([$customer->id => 'up']);

    expect(AiUsage::where('feature', 'call_priority_suggestion')->value('feedback'))->toBe('up');
});

it('forbids generating a talking point for a client the viewer does not own', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->ownedBy($otherSales->id)->create();

    Livewire::actingAs($sales)
        // The row itself would never come from CallPriorityService for this
        // viewer, but the component must not trust a client-forged payload.
        ->test(CallPriorityList::class, ['rows' => [callPriorityRow($customer->id)]])
        ->call('suggestTalkingPoint', $customer->id)
        ->assertForbidden();
});

it('hides the suggest-talking-point button when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->ownedBy($sales->id)->create();

    Livewire::actingAs($sales)
        ->test(CallPriorityList::class, ['rows' => [callPriorityRow($customer->id)]])
        ->assertDontSee('Suggest talking point');
});

it('shows a friendly empty state when the rep has no clients to rank', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(CallPriorityList::class, ['rows' => []])
        ->assertSee('No clients on your book yet');
});
