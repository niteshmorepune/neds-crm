<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Redirect plain-HTTP browser requests to HTTPS in production.
     *
     * Hostinger's edge previously did this via its own "Force HTTPS" toggle,
     * but that redirect also intercepts the eSSL biometric device's ADMS push
     * (registered outside the web group, unaffected by this middleware) and
     * the device can't complete the redirect. Enforcing HTTPS here instead
     * lets the device be reached over plain HTTP at the origin while browser
     * traffic is still always forced to HTTPS.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
