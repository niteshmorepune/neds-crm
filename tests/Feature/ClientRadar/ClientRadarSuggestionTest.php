<?php

use App\Enums\UserRole;
use App\Livewire\ClientRadarSuggestion;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('lets an admin generate and dismiss a client radar suggestion', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Call them this week to check in.']],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($admin)
        ->test(ClientRadarSuggestion::class, [
            'customerId' => $customer->id,
            'flags' => ['no_contact' => ['label' => 'No Contact', 'detail' => 'No contact on record']],
        ])
        ->call('generate')
        ->assertSet('suggestion', 'Call them this week to check in.')
        ->call('rateSuggestion', 'up')
        ->assertSet('suggestionFeedback', 'up')
        ->call('dismiss')
        ->assertSet('suggestion', null)
        ->assertSet('suggestionUsageId', null);

    expect(AiUsage::where('feature', 'client_radar_suggestion')->value('feedback'))->toBe('up');
});

it('forbids a non-manager from generating a client radar suggestion', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(ClientRadarSuggestion::class, [
            'customerId' => $customer->id,
            'flags' => ['no_contact' => ['label' => 'No Contact', 'detail' => 'No contact on record']],
        ])
        ->call('generate')
        ->assertForbidden();
});

it('lets an admin draft and dismiss a CSAT recovery message for the flagged ticket', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "I'm sorry to hear that — let's talk this week."]],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
        ]),
    ]);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create();
    $ticket = Ticket::factory()->for($customer)->create();

    Livewire::actingAs($admin)
        ->test(ClientRadarSuggestion::class, [
            'customerId' => $customer->id,
            'flags' => ['low_satisfaction' => ['label' => 'Low Satisfaction', 'detail' => 'Rated 1/5', 'ticket_id' => $ticket->id]],
        ])
        ->assertSee('Draft recovery message')
        ->call('draftRecovery')
        ->assertSet('recoveryDraft', "I'm sorry to hear that — let's talk this week.")
        ->call('rateRecovery', 'down')
        ->assertSet('recoveryFeedback', 'down')
        ->call('dismissRecovery')
        ->assertSet('recoveryDraft', null)
        ->assertSet('recoveryUsageId', null);

    expect(AiUsage::where('feature', 'csat_recovery_message')->value('feedback'))->toBe('down');
});

it('does not offer a recovery draft when there is no low satisfaction flag', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($admin)
        ->test(ClientRadarSuggestion::class, [
            'customerId' => $customer->id,
            'flags' => ['no_contact' => ['label' => 'No Contact', 'detail' => 'No contact on record']],
        ])
        ->assertDontSee('Draft recovery message');
});

it('forbids a non-manager from drafting a recovery message', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $ticket = Ticket::factory()->for($customer)->create();

    Livewire::actingAs($sales)
        ->test(ClientRadarSuggestion::class, [
            'customerId' => $customer->id,
            'flags' => ['low_satisfaction' => ['label' => 'Low Satisfaction', 'detail' => 'Rated 1/5', 'ticket_id' => $ticket->id]],
        ])
        ->call('draftRecovery')
        ->assertForbidden();
});

it('hides the suggest-action button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($admin)
        ->test(ClientRadarSuggestion::class, [
            'customerId' => $customer->id,
            'flags' => ['no_contact' => ['label' => 'No Contact', 'detail' => 'No contact on record']],
        ])
        ->assertDontSee('Suggest action');
});
