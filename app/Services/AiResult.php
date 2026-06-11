<?php

namespace App\Services;

/**
 * The outcome of a single Claude Messages API call: the concatenated text of
 * all text content blocks plus token usage. Returned by AnthropicClient.
 */
readonly class AiResult
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
