<?php

use App\Models\AiUsage;
use App\Services\AnthropicClient;
use Illuminate\Support\Facades\Http;

it('returns null and makes no request when no API key is configured', function () {
    Http::fake();
    $client = new AnthropicClient(null, 'claude-sonnet-4-20250514');

    expect($client->message('lead_scoring', 'Hello'))->toBeNull();
    Http::assertNothingSent();
    expect(AiUsage::count())->toBe(0);
});

it('concatenates all text blocks and captures token usage', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'Hello '],
                ['type' => 'text', 'text' => 'world'],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 4],
        ]),
    ]);
    $client = new AnthropicClient('sk-test', 'claude-sonnet-4-20250514');

    $result = $client->message('lead_scoring', 'Hi', system: 'Be brief', maxTokens: 50);

    expect($result->text)->toBe('Hello world')
        ->and($result->inputTokens)->toBe(10)
        ->and($result->outputTokens)->toBe(4);
});

it('sends the required Anthropic headers and body shape', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);
    $client = new AnthropicClient('sk-secret', 'claude-sonnet-4-20250514');

    $client->message('lead_scoring', 'Score it', system: 'You score leads', maxTokens: 123);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'sk-secret')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'claude-sonnet-4-20250514'
            && $request['max_tokens'] === 123
            && $request['system'] === 'You score leads'
            && $request['messages'][0] === ['role' => 'user', 'content' => 'Score it'];
    });
});

it('returns null on a non-2xx response and records no usage', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('rate limited', 429)]);
    $client = new AnthropicClient('sk-test', 'claude-sonnet-4-20250514');

    expect($client->message('lead_scoring', 'Hi'))->toBeNull();
    expect(AiUsage::count())->toBe(0);
});
