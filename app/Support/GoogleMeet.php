<?php

namespace App\Support;

/**
 * Feature gate for the Google Meet Notes integration. Phase 1 (OAuth +
 * reading Calendar/Drive) is independent of Ai::enabled() — enabled() alone
 * covers it. Phase 2 (Claude summarization) additionally needs AI_ENABLED,
 * hence the separate summaryEnabled() gate, same idiom as
 * Ai::voiceTranscriptionEnabled() for Call Log voice notes.
 */
class GoogleMeet
{
    public static function enabled(): bool
    {
        return (bool) config('services.google_meet.enabled')
            && filled(config('services.google_meet.client_id'))
            && filled(config('services.google_meet.client_secret'));
    }

    public static function summaryEnabled(): bool
    {
        return self::enabled() && Ai::enabled();
    }
}
