<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public lead-capture API with a shared secret token (from
 * LEAD_CAPTURE_TOKEN). Accepts it as a Bearer token or an X-Lead-Token header.
 */
class VerifyLeadCaptureToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.lead_capture.token');
        $provided = (string) ($request->bearerToken() ?: $request->header('X-Lead-Token', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid or missing API token.'], 401);
        }

        return $next($request);
    }
}
