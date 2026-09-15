<?php

use App\Jobs\MuteWadeskConversationJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
    ]);
});

it('posts the conversationId to wadesk.in with the shared X-Service-Key header', function () {
    Http::fake(['https://wadesk.test/api/conversations/mute' => Http::response(['status' => 'muted'], 200)]);

    (new MuteWadeskConversationJob('conv_abc123'))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://wadesk.test/api/conversations/mute'
        && $request['conversationId'] === 'conv_abc123'
        && $request->hasHeader('X-Service-Key', 'wadesk-secret'));
});

it('logs a warning but does not throw when wadesk.in returns a non-2xx', function () {
    Http::fake(['https://wadesk.test/api/conversations/mute' => Http::response(['error' => 'not found'], 404)]);
    Log::spy();

    (new MuteWadeskConversationJob('conv_missing'))->handle();

    Log::shouldHaveReceived('warning')->once();
});

it('logs a warning but does not throw when the HTTP call itself fails', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    Log::spy();

    (new MuteWadeskConversationJob('conv_timeout'))->handle();

    Log::shouldHaveReceived('warning')->once();
});

it('is a no-op when wadesk config is not set', function () {
    config(['services.wadesk.base_url' => null, 'services.wadesk.service_key' => null]);
    Http::fake();

    (new MuteWadeskConversationJob('conv_no_config'))->handle();

    Http::assertNothingSent();
});

it('is a no-op when the conversation id is blank', function () {
    Http::fake();

    (new MuteWadeskConversationJob(''))->handle();

    Http::assertNothingSent();
});
