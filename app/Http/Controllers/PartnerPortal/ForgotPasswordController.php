<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Http\Controllers\Controller;
use App\Mail\PartnerPasswordReset;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('partner-portal.auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $partner = Partner::where('email', $request->input('email'))
            ->where('portal_enabled', true)
            ->first();

        if ($partner) {
            $token = Str::random(64);

            $partner->forceFill([
                'invitation_token' => hash('sha256', $token),
                'invited_at' => now(),
            ])->save();

            Mail::to($partner->email)->send(new PartnerPasswordReset($partner, $token));
        }

        // Same message whether or not the email exists — prevents enumeration.
        return back()->with('status', 'If that email has portal access, a reset link has been sent. Check your inbox.');
    }
}
