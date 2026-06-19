<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsappWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.whatsapp_webhook.token');
        $provided = (string) ($request->bearerToken() ?: '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
