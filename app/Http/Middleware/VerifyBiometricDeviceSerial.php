<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBiometricDeviceSerial
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.biometric.device_serial');

        if (! $expected || $request->query('SN') !== $expected) {
            return response('Forbidden', 403)->header('Content-Type', 'text/plain');
        }

        return $next($request);
    }
}
