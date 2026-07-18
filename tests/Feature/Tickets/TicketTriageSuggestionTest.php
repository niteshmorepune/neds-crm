<?php

use App\Enums\UserRole;
use App\Livewire\TicketTriageSuggestion;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function fakeTriageAi(string $json): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $json]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15],
        ]),
    ]);
}

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $this->support = User::factory()->role(UserRole::Support)->create();
});

it('suggests a priority, service, and assignee and dispatches the result to the form', function () {
    $service = Service::factory()->create(['name' => 'SEO']);
    $customer = Customer::factory()->create();
    $lead = User::factory()->role(UserRole::Support)->create(['name' => 'Priya']);
    $project = Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);
    $project->assignees()->attach($lead->id, ['role' => 'lead']);

    fakeTriageAi('{"priority": "urgent", "service": "SEO", "reason": "Rankings dropped sharply."}');

    Livewire::actingAs($this->support)
        ->test(TicketTriageSuggestion::class)
        ->call('suggest', $customer->id, 'Rankings dropped', 'Our top keywords fell off page 1 overnight.')
        ->assertSet('priorityLabel', 'Urgent')
        ->assertSet('serviceName', 'SEO')
        ->assertSet('assigneeName', 'Priya')
        ->assertSet('error', null)
        ->assertDispatched('triage-suggested', priority: 'urgent', serviceId: $service->id, assigneeId: $lead->id);
});

it('shows a friendly message instead of calling AI when subject or description is blank', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->support)
        ->test(TicketTriageSuggestion::class)
        ->call('suggest', $customer->id, '', 'Some description')
        ->assertSet('error', 'Fill in the subject and description first.');

    Http::fake();
    Http::assertNothingSent();
});

it('shows a friendly message when no client is selected yet', function () {
    Livewire::actingAs($this->support)
        ->test(TicketTriageSuggestion::class)
        ->call('suggest', null, 'A subject', 'A description')
        ->assertSet('error', 'Select a client first.');
});

it('shows a friendly message when the AI has nothing to suggest for this client', function () {
    $customer = Customer::factory()->create(); // no active projects

    Livewire::actingAs($this->support)
        ->test(TicketTriageSuggestion::class)
        ->call('suggest', $customer->id, 'A subject', 'A description')
        ->assertSet('error', 'Could not suggest a triage for this client right now.')
        ->assertNotDispatched('triage-suggested');
});

it('forbids a role without ticket-create permission from suggesting triage', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($accounts)
        ->test(TicketTriageSuggestion::class)
        ->call('suggest', $customer->id, 'A subject', 'A description')
        ->assertForbidden();
});

it('hides the suggest-triage button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->support)
        ->test(TicketTriageSuggestion::class)
        ->assertDontSee('Suggest priority');
});

it('renders the suggest-triage widget on the new ticket page', function () {
    $this->actingAs($this->support)
        ->get(route('tickets.create'))
        ->assertOk()
        ->assertSee('Suggest priority');
});
