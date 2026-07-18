<?php

namespace App\Services;

/**
 * The outcome of a single Claude Messages API call: the concatenated text of
 * all text content blocks plus token usage. Returned by AnthropicClient.
 * usageId is the id of the ai_usages row this call wrote — callers use it to
 * record thumbs up/down feedback against this exact call later.
 */
readonly class AiResult
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public int $usageId,
    ) {}
}
