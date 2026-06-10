<?php

namespace App\Services;

use App\Models\AiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over the Anthropic (Claude) Messages API, called via the Laravel
 * HTTP client (no SDK, for shared-hosting simplicity).
 *
 * Every call is wrapped in try/catch and returns null on any failure — an AI
 * outage must never break a core workflow (CLAUDE.md). Request and response
 * bodies are NEVER logged: they contain customer data. On success a row is
 * written to `ai_usages` for cost tracking.
 */
class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * Send a single-turn message and return the assistant's text + token usage,
     * or null if the call failed (no key, network error, non-2xx, bad shape).
     *
     * @param  string  $feature  Short identifier recorded on the ai_usages row
     *                           (e.g. "lead_scoring"). Never include PII here.
     */
    public function message(string $feature, string $prompt, ?string $system = null, int $maxTokens = 1000): ?AiResult
    {
        if (blank($this->apiKey)) {
            return null;
        }

        try {
            $payload = [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];

            if (filled($system)) {
                $payload['system'] = $system;
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->timeout(30)
                ->retry(1, 250, throw: false)
                ->post(self::ENDPOINT, $payload);

            if ($response->failed()) {
                // Status only — never the body (may echo the prompt/customer data).
                Log::warning('Anthropic API call failed.', [
                    'feature' => $feature,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            $text = collect($data['content'] ?? [])
                ->where('type', 'text')
                ->pluck('text')
                ->implode('');

            $inputTokens = (int) data_get($data, 'usage.input_tokens', 0);
            $outputTokens = (int) data_get($data, 'usage.output_tokens', 0);

            AiUsage::create([
                'feature' => $feature,
                'model' => $this->model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ]);

            return new AiResult($text, $inputTokens, $outputTokens);
        } catch (Throwable $e) {
            // Message only — Guzzle exception messages do not include our body.
            Log::warning('Anthropic API call threw an exception.', [
                'feature' => $feature,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
