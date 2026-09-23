<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CRM -> wadesk.in half of the two-way contact-name sync (2026-09-23): when
 * a Lead or a Client's Contact is renamed here, the matching wadesk.in
 * Contact (matched by last 10 digits of any of $phones) gets the same name,
 * so the team never sees two different names for one person across the two
 * apps. The reverse half is WadeskContactNameController.
 *
 * Update-only on wadesk.in's side (POST /api/contacts/sync-name never
 * creates a Contact) and it never notifies the CRM back, so there's no
 * echo loop. A rename that itself ARRIVED from wadesk.in is applied inside
 * withoutPushing(), so it isn't bounced straight back either.
 *
 * No-ops (logs, never throws) when wadesk config is absent, no phone is
 * usable, or the name is blank/the WhatsApp placeholder — same failure-
 * tolerance discipline as SyncLeadToWadeskJob.
 */
class SyncContactNameToWadeskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    private static bool $pushingSuspended = false;

    /**
     * @param  array<int, string|null>  $phones
     */
    public function __construct(public array $phones, public string $name) {}

    /**
     * Dispatches only when there's a real name and at least one phone —
     * callers don't need to repeat those checks.
     *
     * @param  array<int, string|null>  $phones
     */
    public static function dispatchFor(array $phones, ?string $name): void
    {
        if (self::$pushingSuspended) {
            return;
        }

        $name = trim((string) $name);
        $phones = array_values(array_filter($phones, fn ($phone) => filled($phone)));

        if ($name === '' || $name === Lead::PLACEHOLDER_NAME || $phones === []) {
            return;
        }

        self::dispatch($phones, $name);
    }

    /**
     * Runs $callback without pushing any rename it causes back to wadesk.in.
     */
    public static function withoutPushing(callable $callback): mixed
    {
        $previous = self::$pushingSuspended;
        self::$pushingSuspended = true;

        try {
            return $callback();
        } finally {
            self::$pushingSuspended = $previous;
        }
    }

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key_lead_sync');

        if (! $baseUrl || ! $serviceKey) {
            return;
        }

        // wadesk.in stores contact phone digits-only, no leading "+".
        $digits = array_values(array_unique(array_filter(
            array_map(fn (string $phone) => Phone::digits($phone), $this->phones),
            fn (string $phone) => strlen($phone) >= 10,
        )));

        if ($digits === []) {
            return;
        }

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/contacts/sync-name", [
                    'phones' => $digits,
                    'name' => $this->name,
                ]);

            if (! $response->successful()) {
                Log::warning('SyncContactNameToWadeskJob: wadesk.in returned non-2xx', [
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SyncContactNameToWadeskJob: HTTP call to wadesk.in failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
