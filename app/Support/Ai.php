<?php

namespace App\Support;

/**
 * Feature gate for all AI (Anthropic) functionality. AI is opt-in via the
 * AI_ENABLED flag — when off, no API calls are made and core workflows are
 * unaffected. Always check Ai::enabled() before dispatching AI work.
 */
class Ai
{
    public static function enabled(): bool
    {
        return (bool) config('services.anthropic.enabled')
            && filled(config('services.anthropic.key'));
    }
}
