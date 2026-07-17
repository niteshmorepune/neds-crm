<?php

use App\Livewire\PortalAssistant;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function fakePortalAiText(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 40, 'output_tokens' => 20],
        ]),
    ]);
}

beforeEach(function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $this->customer = Customer::factory()->create(['company_name' => 'Alpha Corp']);
    $this->contact = Contact::factory()->portalUser()->create(['customer_id' => $this->customer->id]);
});

it('answers a question grounded only in the asking contact\'s own account data', function () {
    Invoice::factory()->create(['customer_id' => $this->customer->id, 'invoice_number' => 'NEDS/2026-27/ALPHA', 'total' => 118000]);
    fakePortalAiText('Your invoice NEDS/2026-27/ALPHA is due soon with a balance of ₹1,180.00.');

    Livewire::actingAs($this->contact, 'portal')
        ->test(PortalAssistant::class)
        ->set('question', 'When is my next payment due?')
        ->call('ask')
        ->assertSet('answer', 'Your invoice NEDS/2026-27/ALPHA is due soon with a balance of ₹1,180.00.');

    Http::assertSent(function ($request) {
        $body = $request->body();

        // JSON-encoded body escapes "/" as "\/", so match the unambiguous suffix instead.
        return str_contains($body, 'Alpha Corp') && str_contains($body, 'ALPHA');
    });
});

it('never includes another customer\'s data in the prompt', function () {
    $otherCustomer = Customer::factory()->create(['company_name' => 'Bravo Corp']);
    Invoice::factory()->create(['customer_id' => $otherCustomer->id, 'invoice_number' => 'NEDS/2026-27/BRAVO']);
    Ticket::factory()->create(['customer_id' => $otherCustomer->id, 'subject' => 'Bravo secret issue']);
    fakePortalAiText('I could not find that in your account.');

    Livewire::actingAs($this->contact, 'portal')
        ->test(PortalAssistant::class)
        ->set('question', 'What is my balance?')
        ->call('ask');

    Http::assertSent(function ($request) {
        $body = $request->body();

        return ! str_contains($body, 'Bravo Corp')
            && ! str_contains($body, 'BRAVO')
            && ! str_contains($body, 'Bravo secret issue');
    });
});

it('rate limits after the configured daily question limit and stops calling the API', function () {
    config(['services.anthropic.portal_assistant_daily_limit' => 2]);
    fakePortalAiText('Here you go.');

    $component = Livewire::actingAs($this->contact, 'portal')->test(PortalAssistant::class);

    $component->set('question', 'Question one?')->call('ask')->assertSet('rateLimited', false);
    $component->set('question', 'Question two?')->call('ask')->assertSet('rateLimited', false);
    $component->set('question', 'Question three?')->call('ask')->assertSet('rateLimited', true)->assertSet('answer', null);

    Http::assertSentCount(2);
});

it('requires a non-empty question under 300 characters', function () {
    Livewire::actingAs($this->contact, 'portal')
        ->test(PortalAssistant::class)
        ->set('question', '')
        ->call('ask')
        ->assertHasErrors('question');

    Livewire::actingAs($this->contact, 'portal')
        ->test(PortalAssistant::class)
        ->set('question', str_repeat('a', 301))
        ->call('ask')
        ->assertHasErrors('question');

    Http::assertNothingSent();
});

it('refuses to answer and makes no call when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();

    Livewire::actingAs($this->contact, 'portal')
        ->test(PortalAssistant::class)
        ->set('question', 'Anything?')
        ->call('ask')
        ->assertForbidden();

    Http::assertNothingSent();
});

it('hides the assistant widget entirely when AI is disabled, shows it when enabled', function () {
    config(['services.anthropic.enabled' => false]);
    $this->actingAs($this->contact, 'portal')->get(route('portal.home'))->assertOk()->assertDontSee('Ask about your account');

    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $this->actingAs($this->contact, 'portal')->get(route('portal.home'))->assertOk()->assertSee('Ask about your account');
});

afterEach(function () {
    RateLimiter::clear('portal-assistant:'.($this->contact->id ?? 0));
});
