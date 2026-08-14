<?php

namespace App\Http\Middleware;

use App\Support\RazorpaySignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same verification as VerifyRazorpayWebhookSignature (HMAC-SHA256 over the
 * raw body, X-Razorpay-Signature header) but checked against a DIFFERENT
 * secret — see config('services.razorpay.visibility_audit_webhook_secret').
 */
class VerifyRazorpayVisibilityAuditWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.razorpay.visibility_audit_webhook_secret');
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
