<?php

use App\Enums\UserRole;
use App\Livewire\CallBrief;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    // The two Log a Call page assertions below go through the real
    // `menu.access:calling` route middleware, which needs real menu rows.
    $this->seed(MenuItemsSeeder::class);
});

function fakeCallBriefAi(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "Context: interested in GMB.\nWhere things stand: awaiting decision.\nSuggested opener: Ask if they reviewed the proposal."]],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 15],
        ]),
    ]);
}

it('generates and rates a call brief for a lead', function () {
    fakeCallBriefAi();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    $component = Livewire::actingAs($sales)
        ->test(CallBrief::class, ['leadId' => $lead->id])
        ->call('generate');

    $component->assertSet('brief', "Context: interested in GMB.\nWhere things stand: awaiting decision.\nSuggested opener: Ask if they reviewed the proposal.");

    $component->call('rate', 'up');
    expect(AiUsage::where('feature', 'call_brief')->value('feedback'))->toBe('up');
});

it('generates a call brief for a customer', function () {
    fakeCallBriefAi();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->ownedBy($sales->id)->create();

    Livewire::actingAs($sales)
        ->test(CallBrief::class, ['customerId' => $customer->id])
        ->call('generate')
        ->assertSet('brief', fn ($brief) => str_contains($brief, 'Suggested opener'));
});

it('prefers the lead when both a lead and customer id are somehow given', function () {
    fakeCallBriefAi();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $customer = Customer::factory()->ownedBy($sales->id)->create();

    Livewire::actingAs($sales)
        ->test(CallBrief::class, ['leadId' => $lead->id, 'customerId' => $customer->id])
        ->call('generate');

    Http::assertSent(fn ($request) => str_contains($request->data()['messages'][0]['content'], 'Lead: '));
});

it('forbids generating a brief for a client the viewer cannot view', function () {
    fakeCallBriefAi();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->ownedBy($otherSales->id)->create();

    Livewire::actingAs($sales)
        ->test(CallBrief::class, ['customerId' => $customer->id])
        ->call('generate')
        ->assertForbidden();
});

it('does not render the button when neither a lead nor customer is given', function () {
    fakeCallBriefAi();
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(CallBrief::class)
        ->assertDontSee('Get call brief');
});

it('does not render the button when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    Livewire::actingAs($sales)
        ->test(CallBrief::class, ['leadId' => $lead->id])
        ->assertDontSee('Get call brief');
});

it('shows the call brief button on the Log a Call form when a lead is preselected', function () {
    fakeCallBriefAi();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($sales)->get(route('calls.create', ['lead_id' => $lead->id]))
        ->assertOk()
        ->assertSee('Get call brief');
});

it('does not show the call brief button on the Log a Call form when nothing is preselected', function () {
    fakeCallBriefAi();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('calls.create'))
        ->assertOk()
        ->assertDontSee('Get call brief');
});
