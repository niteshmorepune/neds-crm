<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccountConnection;
use App\Services\GoogleOAuthClient;
use App\Support\GoogleMeet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * "Connect Google Account" flow from the profile page — Admin-only, since
 * 2026-07-25 this is a single company-wide connection (used server-side to
 * create Meet links and read recordings/transcripts on behalf of any staff
 * member), not per-user OAuth like the original Phase 1 design. Restricted
 * to Admin because the connecting account effectively becomes "the NEDS
 * Google account" for every meeting created through the CRM.
 */
class GoogleConnectionController extends Controller
{
    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(GoogleMeet::enabled(), 404);
        abort_unless($request->user()->isAdmin(), 403);

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->oauth->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleMeet::enabled(), 404);
        abort_unless($request->user()->isAdmin(), 403);

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
        abort_unless($request->user()->isAdmin(), 403);

        // Disconnects the company connection, whichever admin originally
        // connected it — not necessarily $request->user()'s own row.
        GoogleAccountConnection::forCompany()?->delete();

        return redirect()->route('profile.edit')->with('status', 'google-disconnected');
    }
}
