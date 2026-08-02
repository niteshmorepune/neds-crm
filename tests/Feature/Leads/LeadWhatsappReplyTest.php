<?php

use App\Jobs\SendWhatsappLeadReplyJob;
use App\Livewire\RecordNotes;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
    ]);

    $this->staff = User::factory()->create();
});

// ──────────────────────────────────────────────────────────────────────────────
// Component-level gating
// ──────────────────────────────────────────────────────────────────────────────

it('offers the send-via-WhatsApp checkbox only for a Lead with an open WhatsApp conversation', function () {
    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_001']);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->assertSet('sendViaWhatsapp', false)
        ->call('canReplyViaWhatsapp')
        ->assertReturned(true);
});

it('does not offer the checkbox for a Lead with no WhatsApp conversation', function () {
    $lead = Lead::factory()->create(['whatsapp_conversation_id' => null]);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->call('canReplyViaWhatsapp')
        ->assertReturned(false);
});

it('does not offer the checkbox for a non-Lead record even with a canManage user', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $customer, 'canManage' => true])
        ->call('canReplyViaWhatsapp')
        ->assertReturned(false);
});

it('does not offer the checkbox without canManage, even on a Lead with a conversation', function () {
    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_002']);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => false, 'canAddNotes' => true])
        ->call('canReplyViaWhatsapp')
        ->assertReturned(false);
});

// ──────────────────────────────────────────────────────────────────────────────
// addNote() dispatch behaviour
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches SendWhatsappLeadReplyJob and flags the note when sendViaWhatsapp is checked', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_003']);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Sending you our SEO package details now.')
        ->set('sendViaWhatsapp', true)
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('sendViaWhatsapp', false); // reset after submit

    $note = $lead->notes()->first();
    expect($note->sent_via_whatsapp)->toBeTrue();

    Queue::assertPushed(SendWhatsappLeadReplyJob::class, fn ($job) => $job->noteId === $note->id);
});

it('does not dispatch when sendViaWhatsapp is left unchecked', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_004']);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Internal note only.')
        ->call('addNote');

    expect($lead->notes()->first()->sent_via_whatsapp)->toBeFalse();
    Queue::assertNotPushed(SendWhatsappLeadReplyJob::class);
});

it('never dispatches for a Lead with no WhatsApp conversation, even if sendViaWhatsapp is somehow set', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['whatsapp_conversation_id' => null]);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'No conversation to reply on.')
        ->set('sendViaWhatsapp', true)
        ->call('addNote');

    expect($lead->notes()->first()->sent_via_whatsapp)->toBeFalse();
    Queue::assertNotPushed(SendWhatsappLeadReplyJob::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the note body and lead conversation_id to wadesk.in with the service key', function () {
    Http::fake(['https://wadesk.test/api/send' => Http::response(['ok' => true], 200)]);

    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_005']);
    $note = Note::factory()->create([
        'notable_type' => Lead::class,
        'notable_id' => $lead->id,
        'user_id' => $this->staff->id,
        'body' => 'Here is the pricing you asked about.',
        'sent_via_whatsapp' => true,
    ]);

    (new SendWhatsappLeadReplyJob($note->id))->handle();

    Http::assertSent(function ($request) use ($note) {
        return $request->url() === 'https://wadesk.test/api/send'
            && $request->header('X-Service-Key')[0] === 'wadesk-secret'
            && $request['conversationId'] === 'conv_lead_005'
            && $request['content'] === $note->body
            && $request['type'] === 'text';
    });
});

it('skips the HTTP call when the note is not flagged sent_via_whatsapp', function () {
    Http::fake();

    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_006']);
    $note = Note::factory()->create([
        'notable_type' => Lead::class,
        'notable_id' => $lead->id,
        'sent_via_whatsapp' => false,
    ]);

    (new SendWhatsappLeadReplyJob($note->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the note is not on a Lead', function () {
    Http::fake();

    $customer = Customer::factory()->create();
    $note = Note::factory()->create([
        'notable_type' => Customer::class,
        'notable_id' => $customer->id,
        'sent_via_whatsapp' => true,
    ]);

    (new SendWhatsappLeadReplyJob($note->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the lead has no whatsapp_conversation_id', function () {
    Http::fake();

    $lead = Lead::factory()->create(['whatsapp_conversation_id' => null]);
    $note = Note::factory()->create([
        'notable_type' => Lead::class,
        'notable_id' => $lead->id,
        'sent_via_whatsapp' => true,
    ]);

    (new SendWhatsappLeadReplyJob($note->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call silently when wadesk config is not set', function () {
    Http::fake();
    config(['services.wadesk.service_key' => null]);

    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_007']);
    $note = Note::factory()->create([
        'notable_type' => Lead::class,
        'notable_id' => $lead->id,
        'sent_via_whatsapp' => true,
    ]);

    (new SendWhatsappLeadReplyJob($note->id))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in returns an error', function () {
    Http::fake(['https://wadesk.test/api/send' => Http::response(['error' => 'Forbidden'], 403)]);

    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_008']);
    $note = Note::factory()->create([
        'notable_type' => Lead::class,
        'notable_id' => $lead->id,
        'sent_via_whatsapp' => true,
    ]);

    expect(fn () => (new SendWhatsappLeadReplyJob($note->id))->handle())->not->toThrow(Throwable::class);
});

it('logs a warning but does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $lead = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_lead_009']);
    $note = Note::factory()->create([
        'notable_type' => Lead::class,
        'notable_id' => $lead->id,
        'sent_via_whatsapp' => true,
    ]);

    expect(fn () => (new SendWhatsappLeadReplyJob($note->id))->handle())->not->toThrow(Throwable::class);
});
