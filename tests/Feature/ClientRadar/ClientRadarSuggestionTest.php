<?php

use App\Enums\UserRole;
use App\Livewire\ClientRadarSuggestion;
use App\Models\Customer;
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
        ->call('dismiss')
        ->assertSet('suggestion', null);
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
