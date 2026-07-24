<?php

namespace App\Http\Controllers;

use App\Services\GoogleOAuthClient;
use App\Support\GoogleMeet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Self-service "Connect Google Account" flow from the profile page — lets a
 * staff member grant the CRM read-only access to their own Calendar/Drive so
 * they can import their own Google Meet notes. Per-user OAuth, not
 * domain-wide delegation.
 */
class GoogleConnectionController extends Controller
{
    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(GoogleMeet::enabled(), 404);

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->oauth->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleMeet::enabled(), 404);

        $expectedState = $request->session()->pull('google_oauth_state');

        if (! $request->filled('code') || ! $request->filled('state') || $request->string('state')->value() !== $expectedState) {
            return redirect()->route('profile.edit')->with('status', 'google-connect-failed');
        }

        $connection = $this->oauth->connect($request->user(), $request->string('code')->value());

        return redirect()->route('profile.edit')
            ->with('status', $connection ? 'google-connected' : 'google-connect-failed');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->googleAccountConnection?->delete();

        return redirect()->route('profile.edit')->with('status', 'google-disconnected');
    }
}
