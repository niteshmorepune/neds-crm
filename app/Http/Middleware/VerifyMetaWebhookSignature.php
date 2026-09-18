<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Meta's X-Hub-Signature-256 header on inbound Lead Ads webhook
 * events: sha256=<hmac_sha256(raw_body, app_secret)>. Only applies to the
 * POST event route — the GET verification handshake authenticates itself via
 * hub.verify_token, handled directly in the controller.
 */
class VerifyMetaWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.meta.app_secret');
        $signature = (string) ($request->header('X-Hub-Signature-256') ?? '');

        if ($secret === '' || $signature === '' || ! str_starts_with($signature, 'sha256=')) {
            $this->logFailure($request, 'missing/malformed signature header');

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            $this->logFailure($request, 'signature mismatch');

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }

    private function logFailure(Request $request, string $reason): void
    {
        Log::warning('Meta webhook auth failed', [
            'reason' => $reason,
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);
    }
}
