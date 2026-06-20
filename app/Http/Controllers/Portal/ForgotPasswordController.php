<?php

namespace App\Http\Controllers\Portal;

use App\Mail\PortalPasswordReset;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('portal.auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $contact = Contact::where('email', $request->input('email'))
            ->where('portal_enabled', true)
            ->first();

        if ($contact) {
            $token = Str::random(64);

            $contact->forceFill([
                'invitation_token' => hash('sha256', $token),
                'invited_at' => now(),
            ])->save();

            Mail::to($contact->email)->send(new PortalPasswordReset($contact, $token));
        }

        // Same message whether or not the email exists — prevents enumeration.
        return back()->with('status', 'If that email has portal access, a reset link has been sent. Check your inbox.');
    }
}
