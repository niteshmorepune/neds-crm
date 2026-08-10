<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function show(string $token): View
    {
        if (! $this->partnerForToken($token)) {
            return view('partner-portal.auth.invalid-link');
        }

        return view('partner-portal.auth.set-password', ['token' => $token]);
    }

    public function showReset(string $token): View
    {
        if (! $this->partnerForToken($token)) {
            return view('partner-portal.auth.invalid-link');
        }

        return view('partner-portal.auth.reset-password', ['token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse|View
    {
        $partner = $this->partnerForToken($token);

        if (! $partner) {
            return view('partner-portal.auth.invalid-link');
        }

        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $partner->forceFill([
            'password' => $request->input('password'), // hashed via cast
            'password_set_at' => now(),
            'invitation_token' => null,
        ])->save();

        Auth::guard('partner')->login($partner);

        return redirect()->route('partner-portal.home');
    }

    /** Null when the token is invalid, already used, or revoked — never a 404. */
    private function partnerForToken(string $token): ?Partner
    {
        return Partner::where('invitation_token', hash('sha256', $token))
            ->where('portal_enabled', true)
            ->first();
    }
}
