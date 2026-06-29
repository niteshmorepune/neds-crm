<?php

use App\Enums\TicketStatus;
use App\Jobs\SendWhatsappReplyJob;
use App\Livewire\TicketReplies;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);

    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
    ]);

    $this->staff = User::factory()->create();
    $this->customer = Customer::factory()->create();

    $this->whatsappTicket = Ticket::factory()->create([
        'customer_id' => $this->customer->id,
        'channel' => 'whatsapp',
        'whatsapp_conversation_id' => 'conv_tier3_001',
        'status' => TicketStatus::Open,
    ]);

    $this->webTicket = Ticket::factory()->create([
        'customer_id' => $this->customer->id,
        'channel' => 'web',
        'status' => TicketStatus::Open,
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Dispatch behaviour
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches SendWhatsappReplyJob when a staff member posts a public reply on a WhatsApp ticket', function () {
    Queue::fake();

    Livewire::actingAs($this->staff)
        ->test(TicketReplies::class, ['ticket' => $this->whatsappTicket, 'canManage' => true])
        ->set('body', 'We are looking into this now.')
        ->set('is_internal', false)
        ->call('addReply');

    Queue::assertPushed(SendWhatsappReplyJob::class, function ($job) {
        return $job->replyId === TicketReply::latest()->first()->id;
    });
});

it('does not dispatch for an internal note on a WhatsApp ticket', function () {
    Queue::fake();

    Livewire::actingAs($this->staff)
        ->test(TicketReplies::class, ['ticket' => $this->whatsappTicket, 'canManage' => true])
        ->set('body', 'Internal note — escalate to dev.')
        ->set('is_internal', true)
        ->call('addReply');

    Queue::assertNotPushed(SendWhatsappReplyJob::class);
});

it('does not dispatch for a public reply on a web-channel ticket', function () {
    Queue::fake();

    Livewire::actingAs($this->staff)
        ->test(TicketReplies::class, ['ticket' => $this->webTicket, 'canManage' => true])
        ->set('body', 'Thanks for reaching out via the web portal.')
        ->set('is_internal', false)
        ->call('addReply');

    Queue::assertNotPushed(SendWhatsappReplyJob::class);
});

it('does not dispatch when the ticket has no whatsapp_conversation_id', function () {
    Queue::fake();

    $ticket = Ticket::factory()->create([
        'customer_id' => $this->customer->id,
        'channel' => 'whatsapp',
        'whatsapp_conversation_id' => null,
        'status' => TicketStatus::Open,
    ]);

    Livewire::actingAs($this->staff)
        ->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->set('body', 'Hello.')
        ->set('is_internal', false)
        ->call('addReply');

    Queue::assertNotPushed(SendWhatsappReplyJob::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the reply body and conversation_id to wadesk.in with the service key', function () {
    Http::fake(['https://wadesk.test/api/send' => Http::response(['ok' => true], 200)]);

    $reply = TicketReply::factory()->create([
        'ticket_id' => $this->whatsappTicket->id,
        'user_id' => $this->staff->id,
        'body' => 'Your issue has been resolved.',
        'is_internal' => false,
    ]);

    (new SendWhatsappReplyJob($reply->id))->handle();

    Http::assertSent(function ($request) use ($reply) {
        return $request->url() === 'https://wadesk.test/api/send'
            && $request->header('X-Service-Key')[0] === 'wadesk-secret'
            && $request['conversationId'] === 'conv_tier3_001'
            && $request['content'] === $reply->body
            && $request['type'] === 'text';
    });
});

it('skips the HTTP call silently when wadesk config is not set', function () {
    Http::fake();
    config(['services.wadesk.service_key' => null]);

    $reply = TicketReply::factory()->create([
        'ticket_id' => $this->whatsappTicket->id,
        'user_id' => $this->staff->id,
        'body' => 'Hello.',
        'is_internal' => false,
    ]);

    (new SendWhatsappReplyJob($reply->id))->handle();

    Http::assertNothingSent();
});

it('skips forwarding a customer portal reply', function () {
    Http::fake();

    $contact = Contact::factory()->create(['customer_id' => $this->customer->id]);

    $reply = TicketReply::factory()->create([
        'ticket_id' => $this->whatsappTicket->id,
        'contact_id' => $contact->id,
        'user_id' => null,
        'body' => 'Customer reply from portal.',
        'is_internal' => false,
    ]);

    (new SendWhatsappReplyJob($reply->id))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in returns an error', function () {
    Http::fake(['https://wadesk.test/api/send' => Http::response(['error' => 'Forbidden'], 403)]);

    $reply = TicketReply::factory()->create([
        'ticket_id' => $this->whatsappTicket->id,
        'user_id' => $this->staff->id,
        'body' => 'Hello.',
        'is_internal' => false,
    ]);

    expect(fn () => (new SendWhatsappReplyJob($reply->id))->handle())->not->toThrow(Throwable::class);
});

it('logs a warning but does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $reply = TicketReply::factory()->create([
        'ticket_id' => $this->whatsappTicket->id,
        'user_id' => $this->staff->id,
        'body' => 'Hello.',
        'is_internal' => false,
    ]);

    expect(fn () => (new SendWhatsappReplyJob($reply->id))->handle())->not->toThrow(Throwable::class);
});
