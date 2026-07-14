<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the manual-sync API (polled/reported by the office-LAN bridge
 * script, not a browser) with a shared secret, same pattern as
 * VerifyLeadCaptureToken. Separate secret from BIOMETRIC_DEVICE_SERIAL —
 * that one authenticates the eSSL device's own ADMS push, this one
 * authenticates the bridge script checking for/reporting on sync requests.
 */
class VerifyBiometricBridgeToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.biometric.bridge_token');
        $provided = (string) ($request->bearerToken() ?: $request->header('X-Bridge-Token', ''));

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid or missing bridge token.'], 401);
        }

        return $next($request);
    }
}
