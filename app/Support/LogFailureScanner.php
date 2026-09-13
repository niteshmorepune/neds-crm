<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Counts occurrences of a specific, known Log::warning() message within a
 * time window, by reading the daily Laravel log file(s) directly — there is
 * no structured DB row for a wadesk.in send failure or a Razorpay
 * order-creation exception to query instead (deliberately not adding one:
 * both call sites are explicitly out of scope to touch, see
 * MonitorOfferFunnelFailures' own docblock). LOG_CHANNEL=stack with a daily
 * driver (config('logging.channels.daily'), confirmed the production
 * setup) writes storage/logs/laravel-YYYY-MM-DD.log — this reads that same
 * naming convention directly, never Log:: itself, so it stays read-only
 * with respect to the app's own logging pipeline.
 *
 * $logDirectory defaults to storage_path('logs') but is constructor-
 * injectable so tests can point this at an isolated temp directory with
 * synthetic fixture lines, instead of depending on real Log:: writes
 * landing in the real log file during a test run.
 */
class LogFailureScanner
{
    private readonly string $logDirectory;

    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? storage_path('logs');
    }

    /**
     * @return array{count: int, reasons: array<string, int>} reasons is a
     *                                                        "reason text" => occurrence-count map, sorted most-common first —
     *                                                        best-effort, extracted from each matching line's own JSON context
     *                                                        (a nested body.error string when present, else an HTTP status,
     *                                                        else a bare error field). Empty when nothing could be parsed out.
     */
    public function countSince(string $needle, Carbon $since): array
    {
        $matches = array_filter(
            $this->linesSince($since),
            fn (string $line) => str_contains($line, $needle),
        );

        return [
            'count' => count($matches),
            'reasons' => $this->extractReasons($matches),
        ];
    }

    /**
     * @return list<string>
     */
    private function linesSince(Carbon $since): array
    {
        $dates = array_unique([$since->toDateString(), Carbon::now()->toDateString()]);

        $lines = [];

        foreach ($dates as $date) {
            $path = $this->logDirectory.'/laravel-'.$date.'.log';

            if (! is_file($path)) {
                continue;
            }

            $fileLines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($fileLines as $line) {
                if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
                    continue;
                }

                if (Carbon::parse($m[1])->gte($since)) {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  list<string>  $matchedLines
     * @return array<string, int>
     */
    private function extractReasons(array $matchedLines): array
    {
        $reasons = [];

        foreach ($matchedLines as $line) {
            if (! preg_match('/(\{.*\})\s*$/', $line, $m)) {
                continue;
            }

            $context = json_decode($m[1], true);

            if (! is_array($context)) {
                continue;
            }

            $reason = null;

            if (is_string($context['body'] ?? null)) {
                $inner = json_decode($context['body'], true);
                $reason = is_array($inner) && is_string($inner['error'] ?? null) ? $inner['error'] : null;
            }

            $reason ??= isset($context['status']) ? 'HTTP '.$context['status'] : null;
            $reason ??= is_string($context['error'] ?? null) ? $context['error'] : null;
            $reason ??= 'unknown';

            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }

        arsort($reasons);

        return $reasons;
    }
}
