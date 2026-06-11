<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The post-login TOTP gate. The user is already authenticated (password
 * passed); this proves possession of the authenticator (or a recovery code)
 * before they reach the app. Success sets a per-session flag the
 * RequireTwoFactor middleware checks.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorAuthentication $tfa) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasTwoFactorEnabled() || $request->session()->get('auth.two_factor_passed')) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        $code = trim($request->string('code')->value());

        if ($this->tfa->verify((string) $user->two_factor_secret, $code) || $this->consumeRecoveryCode($user, $code)) {
            $request->session()->put('auth.two_factor_passed', true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages(['code' => 'The provided two-factor code was invalid.']);
    }

    /** Consume a one-time recovery code (removing it) if it matches. */
    private function consumeRecoveryCode($user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$code])),
        ])->save();

        return true;
    }
}
