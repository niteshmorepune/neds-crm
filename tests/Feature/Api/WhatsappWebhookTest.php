<?php

use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Jobs\ImportWhatsappTicketMedia;
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
