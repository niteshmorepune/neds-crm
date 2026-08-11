<?php

namespace App\Http\Middleware;

use App\Support\RazorpaySignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Razorpay's X-Razorpay-Signature header: HMAC-SHA256 over the raw
 * request body, plain hex (no "sha256=" prefix, unlike Meta's webhook
 * header). Secret is a DIFFERENT value from the website's own Razorpay
 * webhook — see config('services.razorpay') for why.
 */
class VerifyRazorpayWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        $signature = (string) ($request->header('X-Razorpay-Signature') ?? '');

        if ($secret === '' || $signature === '') {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! RazorpaySignature::verifyWebhook($request->getContent(), $signature, $secret)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
