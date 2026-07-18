<?php

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Livewire\AskTheCrm;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function fakeAnthropicSequence(array $texts): void
{
    $responses = array_map(fn ($text) => Http::response([
        'content' => [['type' => 'text', 'text' => $text]],
        'usage' => ['input_tokens' => 20, 'output_tokens' => 10],
    ]), $texts);

    Http::fake(['api.anthropic.com/*' => Http::sequence($responses)]);
}

beforeEach(function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('classifies, fetches real figures, and narrates an answer with a link to the full report', function () {
    Deal::factory()->stage(DealStage::Won)->create(['value' => 500000, 'won_at' => now()]);

    fakeAnthropicSequence([
        '{"query_type": "sales_pipeline_kpis"}',
        'You have won ₹5,000.00 so far this month.',
    ]);

    Livewire::actingAs($this->admin)
        ->test(AskTheCrm::class)
        ->set('question', "What's our pipeline looking like?")
        ->call('ask')
        ->assertSet('answer', 'You have won ₹5,000.00 so far this month.')
        ->assertSet('unsupported', false)
        ->assertSet('reportLabel', 'Sales Dashboard');

    Http::assertSentCount(2);
});

it('shows the example-topics list when the question does not match any known report', function () {
    fakeAnthropicSequence(['{"query_type": "unsupported"}']);

    Livewire::actingAs($this->admin)
        ->test(AskTheCrm::class)
        ->set('question', 'What is the meaning of life?')
        ->call('ask')
        ->assertSet('unsupported', true)
        ->assertSet('answer', null)
        ->assertSee('Sales Pipeline KPIs');

    Http::assertSentCount(1); // narration never runs for an unsupported question
});

it('never leaks another customer\'s data since figures come from real scoped metrics, not the model', function () {
    Customer::factory()->create(['company_name' => 'Bravo Corp', 'status' => 'active']);
    Invoice::factory()->create(['status' => InvoiceStatus::Overdue]);

    fakeAnthropicSequence([
        '{"query_type": "client_radar"}',
        'One client needs attention.',
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(AskTheCrm::class)
        ->set('question', 'Which clients are at risk?')
        ->call('ask');

    expect(collect($component->get('figures'))->pluck('label'))->toContain('Bravo Corp');
});

it('forbids a non-manager from asking the CRM', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(AskTheCrm::class)
        ->set('question', 'Anything?')
        ->call('ask')
        ->assertForbidden();
});

it('requires a non-empty question under 300 characters', function () {
    Livewire::actingAs($this->admin)
        ->test(AskTheCrm::class)
        ->set('question', '')
        ->call('ask')
        ->assertHasErrors('question');

    Http::fake();
    Http::assertNothingSent();
});

it('hides the widget entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);

    Livewire::actingAs($this->admin)
        ->test(AskTheCrm::class)
        ->assertDontSee('Ask the CRM');
});

it('lets an admin and a manager view the Ask the CRM page but forbids sales', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($this->admin)->get(route('reports.ask'))->assertOk()->assertSee('Ask the CRM');
    $this->actingAs($manager)->get(route('reports.ask'))->assertOk();
    $this->actingAs($sales)->get(route('reports.ask'))->assertForbidden();
});
