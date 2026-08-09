<?php

use App\Jobs\ImportWhatsappTicketMedia;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.in',
        'services.wadesk.service_key' => 'test-wadesk-key',
    ]);
    Storage::fake('local');
});

function makeWhatsappTicket(): Ticket
{
    $customer = Customer::factory()->create();

    return Ticket::factory()->for($customer)->create([
        'channel' => 'whatsapp',
        'whatsapp_conversation_id' => 'conv_media_1',
    ]);
}

it('downloads the media and stores it as a real attachment', function () {
    $ticket = makeWhatsappTicket();

    Http::fake([
        'wadesk.in/api/media/media-abc*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    ImportWhatsappTicketMedia::dispatchSync($ticket->id, 'conv_media_1', 'media-abc', 'image');

    $attachment = $ticket->fresh()->attachments()->first();
    expect($attachment)->not->toBeNull()
        ->and($attachment->mime_type)->toBe('image/jpeg')
        ->and($attachment->original_name)->toBe("whatsapp-image-{$ticket->id}.jpg")
        ->and($attachment->size)->toBe(strlen('fake-image-bytes'));

    Storage::disk('local')->assertExists($attachment->path);
    expect(Storage::disk('local')->get($attachment->path))->toBe('fake-image-bytes');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://wadesk.in/api/media/media-abc?conversationId=conv_media_1'
            && $request->hasHeader('X-Service-Key', 'test-wadesk-key');
    });
});

it('does nothing when the wadesk fetch fails', function () {
    $ticket = makeWhatsappTicket();

    Http::fake([
        'wadesk.in/api/media/*' => Http::response('not found', 404),
    ]);

    ImportWhatsappTicketMedia::dispatchSync($ticket->id, 'conv_media_1', 'media-abc', 'image');

    expect($ticket->fresh()->attachments()->count())->toBe(0);
});

it('does nothing when no wadesk service key is configured', function () {
    config(['services.wadesk.service_key' => null]);
    $ticket = makeWhatsappTicket();

    Http::fake();

    ImportWhatsappTicketMedia::dispatchSync($ticket->id, 'conv_media_1', 'media-abc', 'image');

    expect($ticket->fresh()->attachments()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the ticket no longer exists', function () {
    Http::fake();

    ImportWhatsappTicketMedia::dispatchSync(999999, 'conv_media_1', 'media-abc', 'image');

    Http::assertNothingSent();
});
