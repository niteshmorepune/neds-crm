<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('partner-portal.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Only portal-enabled partners may authenticate.
        $ok = Auth::guard('partner')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'portal_enabled' => true,
        ], $request->boolean('remember'));

        if (! $ok) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('partner-portal.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('partner')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('partner-portal.login');
    }
}
