<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySmdostWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.smdost.service_key');
        $provided = (string) ($request->bearerToken() ?: '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
