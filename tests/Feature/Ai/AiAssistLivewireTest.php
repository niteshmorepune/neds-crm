<?php

use App\Enums\UserRole;
use App\Livewire\ClientNotes;
use App\Livewire\RecordNotes;
use App\Livewire\TicketReplies;
use App\Models\AiUsage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Records are built with AI off so the lead-scoring observer stays quiet. */
function withAi(callable $build): mixed
{
    config(['services.anthropic.enabled' => false]);
    $record = $build();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);

    return $record;
}

function fakeReply(string $text): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => $text]],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
    ])]);
}

it('ticket: AI draft drops into the reply box without sending', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = withAi(fn () => Ticket::factory()->create());
    fakeReply('Thanks for reaching out — your account is now active.');

    Livewire::actingAs($support)
        ->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->call('draftReply')
        ->assertHasNoErrors()
        ->assertSet('body', 'Thanks for reaching out — your account is now active.');

    // Nothing was persisted as a reply — drafting never sends.
    expect($ticket->replies()->count())->toBe(0);
});

it('ticket: a drafted reply can be rated, and the rating persists on ai_usages', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = withAi(fn () => Ticket::factory()->create());
    fakeReply('Thanks for reaching out — your account is now active.');

    Livewire::actingAs($support)
        ->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->call('draftReply')
        ->assertSet('draftFeedback', null)
        ->call('rateDraft', 'up')
        ->assertSet('draftFeedback', 'up')
        ->assertSee('Thanks for the feedback');

    expect(AiUsage::where('feature', 'draft_ticket_reply')->value('feedback'))->toBe('up');
});

it('ticket: sending the reply clears the draft rating state', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = withAi(fn () => Ticket::factory()->create());
    fakeReply('Thanks for reaching out.');

    Livewire::actingAs($support)
        ->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->call('draftReply')
        ->call('addReply')
        ->assertSet('draftUsageId', null)
        ->assertSet('draftFeedback', null);
});

it('ticket: summarize fills the panel and can be dismissed', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = withAi(fn () => Ticket::factory()->create());
    fakeReply('- Client reported a login issue. - Resolved by reset.');

    $component = Livewire::actingAs($support)
        ->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->call('summarize')
        ->assertSet('summary', '- Client reported a login issue. - Resolved by reset.')
        ->assertSee('AI summary');

    $component->call('rateSummary', 'down')->assertSet('summaryFeedback', 'down');
    expect(AiUsage::where('feature', 'summarize_ticket')->value('feedback'))->toBe('down');

    $component->call('dismissSummary')->assertSet('summary', null)->assertSet('summaryFeedback', null);
});

it('ticket: AI buttons are hidden when the flag is off', function () {
    config(['services.anthropic.enabled' => false]);
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create();

    Livewire::actingAs($support)
        ->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->assertSet('aiEnabled', false)
        ->assertDontSee('Draft with AI')
        ->assertDontSee('Summarize thread');
});

it('customer: summarize fills the panel', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = withAi(fn () => Customer::factory()->create());
    fakeReply('- Long-standing retainer client. - No open issues.');

    Livewire::actingAs($manager)
        ->test(ClientNotes::class, ['customer' => $customer, 'canManage' => true])
        ->call('summarize')
        ->assertSet('summary', '- Long-standing retainer client. - No open issues.')
        ->assertSee('AI summary')
        ->call('rateSummary', 'up')
        ->assertSet('summaryFeedback', 'up');

    expect(AiUsage::where('feature', 'summarize_customer')->value('feedback'))->toBe('up');
});

it('lead: AI follow-up draft drops into the note box', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = withAi(fn () => Lead::factory()->create(['name' => 'Meera']));
    fakeReply('Hi Meera, following up on your website enquiry — when suits a quick call?');

    Livewire::actingAs($admin)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->assertSet('record.id', $lead->id)
        ->call('draftFollowUp')
        ->assertHasNoErrors()
        ->assertSet('body', 'Hi Meera, following up on your website enquiry — when suits a quick call?')
        ->call('rateDraft', 'down')
        ->assertSet('draftFeedback', 'down');

    expect(AiUsage::where('feature', 'draft_lead_followup')->value('feedback'))->toBe('down');
});

it('record-notes: drafting is offered for leads but not deals', function () {
    $lead = withAi(fn () => Lead::factory()->create());
    $deal = Deal::factory()->create();

    expect(Livewire::test(RecordNotes::class, ['record' => $lead, 'canManage' => true])->instance()->canDraft())->toBeTrue();
    expect(Livewire::test(RecordNotes::class, ['record' => $deal, 'canManage' => true])->instance()->canDraft())->toBeFalse();
});
