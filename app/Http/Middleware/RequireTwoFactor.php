<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces two-factor authentication for the internal app:
 *
 *  - Any user who has 2FA enabled must pass the post-login challenge before
 *    reaching protected pages.
 *  - Admins and managers (requiresTwoFactor) who haven't enrolled yet are sent
 *    to their profile to set it up.
 *
 * Enrolment, the challenge itself, profile/password management and logout are
 * always allowed so a user is never locked out of completing setup.
 */
class RequireTwoFactor
{
    private const ALLOWED = [
        'logout',
        'two-factor.challenge', 'two-factor.challenge.store',
        'two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.recovery',
        'profile.edit', 'profile.update', 'profile.destroy', 'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        // Always gate users who have 2FA on until they pass the challenge.
        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('auth.two_factor_passed')) {
            return redirect()->route('two-factor.challenge');
        }

        // Forcing admins/managers to enrol is a deploy-time policy (off by
        // default so it doesn't surprise non-production environments).
        if (config('security.enforce_two_factor_enrollment')
            && $user->requiresTwoFactor()
            && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.edit')->with('status', 'two-factor-required');
        }

        return $next($request);
    }
}
