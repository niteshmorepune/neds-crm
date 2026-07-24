<?php

namespace App\Support;

/**
 * Feature gate for the Google Meet Notes integration (Phase 1). Independent
 * of Ai::enabled() — this feature has no AI step yet, just OAuth + reading
 * Calendar/Drive. Checked wherever "Connect Google Account" or "Import Meet
 * Notes" could be shown, so a half-configured OAuth app never invites a
 * connection attempt that can only fail.
 */
class GoogleMeet
{
    public static function enabled(): bool
    {
        return (bool) config('services.google_meet.enabled')
            && filled(config('services.google_meet.client_id'))
            && filled(config('services.google_meet.client_secret'));
    }
}
