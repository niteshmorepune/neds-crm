<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Self-service TOTP enrolment from the profile page. A secret is stored on
 * "enable" but only takes effect (two_factor_confirmed_at) once the user proves
 * they can generate a valid code — so a half-finished setup never locks anyone
 * out. Recovery codes are shown once, right after confirmation.
 */
class TwoFactorSetupController extends Controller
{
    public function __construct(private readonly TwoFactorAuthentication $tfa) {}

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Generate a fresh, unconfirmed secret. Re-running before confirming
        // simply rolls a new secret.
        $user->forceFill([
            'two_factor_secret' => $this->tfa->generateSecret(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', 'two-factor-setup');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (blank($user->two_factor_secret) || ! $this->tfa->verify($user->two_factor_secret, $request->string('code')->value())) {
            throw ValidationException::withMessages(['code' => 'That code is invalid. Try the current code from your app.']);
        }

        $codes = $this->tfa->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        // The challenge gate is satisfied for this session immediately after setup.
        $request->session()->put('auth.two_factor_passed', true);

        return back()->with('recovery_codes', $codes)->with('status', 'two-factor-enabled');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', 'two-factor-disabled');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 403);

        $codes = $this->tfa->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return back()->with('recovery_codes', $codes)->with('status', 'recovery-codes-generated');
    }
}
