<?php

use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\User;

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
