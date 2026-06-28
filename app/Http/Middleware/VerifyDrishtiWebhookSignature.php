<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDrishtiWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret    = (string) config('services.drishti.webhook_secret');
        $signature = (string) ($request->header('X-Agency-Signature') ?? '');
        $timestamp = (string) ($request->header('X-Agency-Timestamp') ?? '');

        if ($secret === '' || $signature === '' || $timestamp === '') {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // Reject replayed requests older than 5 minutes.
        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return response()->json(['message' => 'Request expired.'], 401);
        }

        $body     = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
