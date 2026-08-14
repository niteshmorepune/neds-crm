<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Jobs\ImportWhatsappTicketMedia;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config(['services.whatsapp_webhook.token' => 'test-wa-token']);
});

it('creates a ticket when a matching customer is found by phone', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'contact_name' => 'Ravi Kumar',
        'message' => 'Hi, I need help with my project.',
        'conversation_id' => 'conv_abc123',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_abc123')->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->customer_id)->toBe($customer->id)
        ->and($ticket->channel)->toBe('whatsapp')
        ->and($ticket->subject)->toStartWith('WhatsApp:');
});

it('matches customer by last 10 digits when CRM stores local number', function () {
    $customer = Customer::factory()->create(['phone' => '9028099919']); // 10-digit local

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919', // wadesk.in sends full international
        'contact_name' => 'Ravi',
        'message' => 'Hello',
        'conversation_id' => 'conv_local_match',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    expect(Ticket::where('whatsapp_conversation_id', 'conv_local_match')->exists())->toBeTrue();
});

it('creates a ticket when the message comes from a client\'s alternate_phone, not their primary phone', function () {
    // Real incident (2026-08-13): "Top Fruit Exports" messaged from a
    // second number recorded only as alternate_phone — this lookup didn't
    // check it yet, so the message wrongly created a Lead instead of a Ticket.
    $customer = Customer::factory()->create(['phone' => '9604454564', 'alternate_phone' => '+919270279886']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919270279886',
        'message' => 'Hello, following up on our order',
        'conversation_id' => 'conv_alt_phone',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_alt_phone')->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->customer_id)->toBe($customer->id);
});

it('creates a ticket when the message comes from an individual Contact\'s phone, not the company-level Customer phone', function () {
    $customer = Customer::factory()->create(['phone' => '9604454564', 'alternate_phone' => null]);
    Contact::factory()->for($customer)->create(['phone' => '9270279886']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919270279886',
        'message' => 'Hi, this is Rakesh from the office',
        'conversation_id' => 'conv_contact_phone',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_contact_phone')->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->customer_id)->toBe($customer->id);
});

it('deduplicates — second call for same conversation_id returns duplicate status', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);

    $payload = [
        'phone' => '919028099919',
        'message' => 'Hello',
        'conversation_id' => 'conv_dedup',
    ];

    $this->postJson('/api/webhook/whatsapp', $payload, ['Authorization' => 'Bearer test-wa-token'])
        ->assertJson(['status' => 'created']);

    $this->postJson('/api/webhook/whatsapp', $payload, ['Authorization' => 'Bearer test-wa-token'])
        ->assertJson(['status' => 'duplicate']);

    expect(Ticket::where('whatsapp_conversation_id', 'conv_dedup')->count())->toBe(1);
});

it('creates a lead when phone does not match any customer', function () {
    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'contact_name' => 'Unknown Caller',
        'message' => 'Hi, interested in your services',
        'conversation_id' => 'conv_unknown',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'lead_created']);

    expect(Ticket::where('whatsapp_conversation_id', 'conv_unknown')->exists())->toBeFalse();

    $lead = Lead::where('whatsapp_conversation_id', 'conv_unknown')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->name)->toBe('Unknown Caller')
        ->and($lead->phone)->toBe('919999999999')
        ->and($lead->source)->toBe(LeadSource::Whatsapp)
        ->and($lead->notes()->count())->toBe(1)
        ->and($lead->notes()->first()->body)->toBe('Hi, interested in your services');
});

it('falls back to a generic name when contact_name is missing', function () {
    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'Hello',
        'conversation_id' => 'conv_no_name',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    expect(Lead::where('whatsapp_conversation_id', 'conv_no_name')->first()->name)
        ->toBe('WhatsApp Inquiry');
});

it('adds a note to the existing lead on a later message in the same conversation, without creating a second lead', function () {
    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'First message',
        'conversation_id' => 'conv_repeat',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'lead_created']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'Second message',
        'conversation_id' => 'conv_repeat',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'lead_note_added']);

    expect(Lead::where('whatsapp_conversation_id', 'conv_repeat')->count())->toBe(1);

    $lead = Lead::where('whatsapp_conversation_id', 'conv_repeat')->first();
    expect($lead->notes()->count())->toBe(2)
        ->and($lead->notes()->pluck('body'))->toContain('First message', 'Second message');
});

it('auto-assigns a WhatsApp-sourced lead the same way as any other new lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'Hello',
        'conversation_id' => 'conv_autoassign',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    expect(Lead::where('whatsapp_conversation_id', 'conv_autoassign')->first()->owner_id)
        ->toBe($sales->id);
});

it('attaches a WhatsApp message to an existing open lead with the same phone from a different channel, instead of creating a duplicate', function () {
    $metaLead = Lead::factory()->create([
        'phone' => '919999999999',
        'source' => LeadSource::MetaAds,
        'whatsapp_conversation_id' => null,
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'Hello! I filled out your form and would like to know more.',
        'conversation_id' => 'conv_meta_followup',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'lead_note_added', 'lead_id' => $metaLead->id]);

    expect(Lead::count())->toBe(1)
        ->and($metaLead->fresh()->whatsapp_conversation_id)->toBe('conv_meta_followup')
        ->and($metaLead->fresh()->source)->toBe(LeadSource::MetaAds) // attribution unchanged
        ->and($metaLead->notes()->first()->body)->toContain('Hello! I filled out your form');
});

it('does not match a Converted or Lost lead by phone — creates a genuinely new lead instead', function () {
    Lead::factory()->create(['phone' => '919999999999', 'status' => LeadStatus::Converted]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'Hi, interested again',
        'conversation_id' => 'conv_repeat_customer',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertJson(['status' => 'lead_created']);

    expect(Lead::count())->toBe(2);
});

it('rejects requests without the correct token', function () {
    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Hello',
        'conversation_id' => 'conv_unauth',
    ])->assertUnauthorized();
});

it('appends a Drishti context link to the description when the customer has drishti_client_id', function () {
    config(['services.drishti.base_url' => 'https://nedsdrishti.in']);
    $customer = Customer::factory()->create(['phone' => '919028099919', 'drishti_client_id' => 'drishti-abc']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Need help with my audit.',
        'conversation_id' => 'conv_drishti_link',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_drishti_link')->first();
    expect($ticket->description)->toContain('https://nedsdrishti.in/clients/drishti-abc');
});

it('does not append a Drishti link when the customer has no drishti_client_id', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919', 'drishti_client_id' => null]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Need help.',
        'conversation_id' => 'conv_no_drishti',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_no_drishti')->first();
    expect($ticket->description)->not->toContain('drishti');
});

it('routes a message on a non-support line to the Lead flow even when the phone matches an existing Customer', function () {
    config(['services.wadesk.support_number' => '918007733737']);
    $customer = Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'contact_name' => 'Ravi Kumar',
        'message' => 'Hi, tell me about your SEO packages',
        'conversation_id' => 'conv_marketing_known_customer',
        'whatsapp_number' => '919112095202',
        'whatsapp_line_label' => 'Marketing',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'lead_created']);

    expect(Ticket::where('whatsapp_conversation_id', 'conv_marketing_known_customer')->exists())->toBeFalse();

    $lead = Lead::where('whatsapp_conversation_id', 'conv_marketing_known_customer')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->phone)->toBe('919028099919');
});

it('still creates a Ticket when whatsapp_number explicitly matches the configured support line', function () {
    config(['services.wadesk.support_number' => '918007733737']);
    $customer = Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Need help with an invoice',
        'conversation_id' => 'conv_explicit_support_line',
        'whatsapp_number' => '918007733737',
        'whatsapp_line_label' => 'Support',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    expect(Ticket::where('whatsapp_conversation_id', 'conv_explicit_support_line')->exists())->toBeTrue();
});

it('dispatches ImportWhatsappTicketMedia and gives a caption-less media message a friendly subject/description instead of the raw placeholder', function () {
    Bus::fake();
    $customer = Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => '[image]',
        'conversation_id' => 'conv_media',
        'media_id' => 'media-xyz',
        'media_type' => 'image',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_media')->first();
    expect($ticket->subject)->toBe('WhatsApp: Image received')
        ->and($ticket->description)->toContain('Image received — see Attachments below.')
        ->and($ticket->description)->not->toContain('[image]');

    Bus::assertDispatched(ImportWhatsappTicketMedia::class, function ($job) use ($ticket) {
        return $job->ticketId === $ticket->id
            && $job->conversationId === 'conv_media'
            && $job->mediaId === 'media-xyz'
            && $job->mediaType === 'image';
    });
});

it('does not dispatch ImportWhatsappTicketMedia for a plain text message', function () {
    Bus::fake();
    Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Hello, need help',
        'conversation_id' => 'conv_text_only',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    Bus::assertNotDispatched(ImportWhatsappTicketMedia::class);
});

it('keeps a real caption as the subject/description even when the message has media', function () {
    Bus::fake();
    Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Here is the error I am seeing',
        'conversation_id' => 'conv_media_captioned',
        'media_id' => 'media-xyz',
        'media_type' => 'image',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_media_captioned')->first();
    expect($ticket->subject)->toBe('WhatsApp: Here is the error I am seeing')
        ->and($ticket->description)->toContain('Here is the error I am seeing');
});

it('treats a payload with no whatsapp_number field as the support line, for backward compatibility with an older wadesk.in build', function () {
    config(['services.wadesk.support_number' => '918007733737']);
    $customer = Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Need help',
        'conversation_id' => 'conv_no_line_field',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    expect(Ticket::where('whatsapp_conversation_id', 'conv_no_line_field')->exists())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// Full-conversation capture — every message, both directions (a wadesk.in
// build that tags message_id/direction/sender_type)
// ──────────────────────────────────────────────────────────────────────────────

it('appends a customer\'s later inbound message on an existing ticket as a reply, not a second ticket', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'contact_name' => 'Ravi Kumar',
        'message' => 'Hi, I need help',
        'conversation_id' => 'conv_full_capture',
        'message_id' => 'wa_msg_1',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'created']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'contact_name' => 'Ravi Kumar',
        'message' => 'Any update?',
        'conversation_id' => 'conv_full_capture',
        'message_id' => 'wa_msg_2',
        'direction' => 'inbound',
        'sender_type' => 'customer',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'reply_added']);

    expect(Ticket::where('whatsapp_conversation_id', 'conv_full_capture')->count())->toBe(1);

    $ticket = Ticket::where('whatsapp_conversation_id', 'conv_full_capture')->first();
    $reply = $ticket->replies()->latest()->first();
    expect($reply->body)->toBe('Any update?')
        ->and($reply->whatsapp_direction)->toBe('inbound')
        ->and($reply->external_sender_name)->toBe('Ravi Kumar')
        ->and($reply->isFromCustomer())->toBeTrue()
        ->and($reply->authorName())->toBe('Ravi Kumar')
        ->and($reply->is_internal)->toBeFalse();
});

it('appends a human agent\'s outbound reply (sent from wadesk.in directly, not via the CRM) as a reply attributed to them', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);
    $ticket = Ticket::factory()->for($customer)->create(['whatsapp_conversation_id' => 'conv_agent_reply']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Sure, let me check that for you.',
        'conversation_id' => 'conv_agent_reply',
        'message_id' => 'wa_msg_agent_1',
        'direction' => 'outbound',
        'sender_type' => 'agent',
        'sender_name' => 'Kiran Katte',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'reply_added']);

    $reply = $ticket->replies()->latest()->first();
    expect($reply->whatsapp_direction)->toBe('outbound')
        ->and($reply->external_sender_name)->toBe('Kiran Katte')
        ->and($reply->isFromCustomer())->toBeFalse()
        ->and($reply->authorName())->toBe('Kiran Katte');
});

it('attributes an AI after-hours auto-reply to "AI Assistant" rather than a blank/System author', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);
    $ticket = Ticket::factory()->for($customer)->create(['whatsapp_conversation_id' => 'conv_ai_reply']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Thanks for reaching out — our team will respond during business hours.',
        'conversation_id' => 'conv_ai_reply',
        'message_id' => 'wa_msg_ai_1',
        'direction' => 'outbound',
        'sender_type' => 'ai',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'reply_added']);

    $reply = $ticket->replies()->latest()->first();
    expect($reply->external_sender_name)->toBe('AI Assistant (WhatsApp)')
        ->and($reply->authorName())->toBe('AI Assistant (WhatsApp)');
});

it('reopens a Resolved ticket when the customer messages again', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);
    $ticket = Ticket::factory()->for($customer)->create([
        'whatsapp_conversation_id' => 'conv_reopen',
        'status' => TicketStatus::Resolved,
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Actually the issue is back',
        'conversation_id' => 'conv_reopen',
        'message_id' => 'wa_msg_reopen',
        'direction' => 'inbound',
        'sender_type' => 'customer',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

it('does not reopen a Resolved ticket for a staff/AI outbound message', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);
    $ticket = Ticket::factory()->for($customer)->create([
        'whatsapp_conversation_id' => 'conv_no_reopen',
        'status' => TicketStatus::Resolved,
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'Just following up, no action needed.',
        'conversation_id' => 'conv_no_reopen',
        'message_id' => 'wa_msg_no_reopen',
        'direction' => 'outbound',
        'sender_type' => 'agent',
        'sender_name' => 'Kiran Katte',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved);
});

it('ignores a message wadesk.in tags as sent by the CRM itself — already recorded when the CRM sent it', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);
    $ticket = Ticket::factory()->for($customer)->create(['whatsapp_conversation_id' => 'conv_own_send']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919028099919',
        'message' => 'This was already saved as a TicketReply by the CRM before sending.',
        'conversation_id' => 'conv_own_send',
        'message_id' => 'wa_msg_crm_echo',
        'direction' => 'outbound',
        'sender_type' => 'crm',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'ignored', 'reason' => 'own_send']);

    expect($ticket->replies()->count())->toBe(0);
});

it('dedupes a retried webhook delivery by message_id, even across a status transition in between', function () {
    $customer = Customer::factory()->create(['phone' => '919028099919']);
    $ticket = Ticket::factory()->for($customer)->create(['whatsapp_conversation_id' => 'conv_msg_dedup']);

    $payload = [
        'phone' => '919028099919',
        'message' => 'One message, delivered twice',
        'conversation_id' => 'conv_msg_dedup',
        'message_id' => 'wa_msg_retry',
        'direction' => 'inbound',
        'sender_type' => 'customer',
    ];

    $this->postJson('/api/webhook/whatsapp', $payload, ['Authorization' => 'Bearer test-wa-token'])
        ->assertJson(['status' => 'reply_added']);
    $this->postJson('/api/webhook/whatsapp', $payload, ['Authorization' => 'Bearer test-wa-token'])
        ->assertJson(['status' => 'duplicate']);

    expect($ticket->replies()->count())->toBe(1);
});

it('formats an outbound agent message on a Lead as a clearly-attributed note, not a raw echo', function () {
    $lead = Lead::factory()->create(['phone' => '919999999999', 'whatsapp_conversation_id' => 'conv_lead_agent_reply']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'Sure, I can share pricing over a quick call.',
        'conversation_id' => 'conv_lead_agent_reply',
        'message_id' => 'wa_msg_lead_agent',
        'direction' => 'outbound',
        'sender_type' => 'agent',
        'sender_name' => 'Priya Shah',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'lead_note_added']);

    $note = $lead->notes()->latest()->first();
    expect($note->body)->toBe("[Sent via WhatsApp by Priya Shah]\nSure, I can share pricing over a quick call.");
});

it('formats an AI auto-reply on a Lead as a clearly-attributed note', function () {
    $lead = Lead::factory()->create(['phone' => '919999999999', 'whatsapp_conversation_id' => 'conv_lead_ai_reply']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'Thanks for reaching out!',
        'conversation_id' => 'conv_lead_ai_reply',
        'message_id' => 'wa_msg_lead_ai',
        'direction' => 'outbound',
        'sender_type' => 'ai',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $note = $lead->notes()->latest()->first();
    expect($note->body)->toBe("[Sent via WhatsApp by AI Assistant (auto-reply)]\nThanks for reaching out!");
});

it('leaves an inbound Lead message body unprefixed, same as before this feature', function () {
    $lead = Lead::factory()->create(['phone' => '919999999999', 'whatsapp_conversation_id' => 'conv_lead_inbound_tagged']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919999999999',
        'message' => 'What are your charges for GMB?',
        'conversation_id' => 'conv_lead_inbound_tagged',
        'message_id' => 'wa_msg_lead_inbound',
        'direction' => 'inbound',
        'sender_type' => 'customer',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    expect($lead->notes()->latest()->first()->body)->toBe('What are your charges for GMB?');
});
