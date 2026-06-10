<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function show(string $token): View
    {
        $this->contactForToken($token); // 404 if invalid

        return view('portal.auth.set-password', ['token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $contact = $this->contactForToken($token);

        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $contact->forceFill([
            'password' => $request->input('password'), // hashed via cast
            'password_set_at' => now(),
            'invitation_token' => null,
        ])->save();

        Auth::guard('portal')->login($contact);

        return redirect()->route('portal.home');
    }

    private function contactForToken(string $token): Contact
    {
        return Contact::where('invitation_token', hash('sha256', $token))
            ->where('portal_enabled', true)
            ->firstOrFail();
    }
}
