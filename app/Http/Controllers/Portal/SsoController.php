<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\PortalSsoToken;
use Illuminate\Http\RedirectResponse;

class SsoController extends Controller
{
    /**
     * Generate a short-lived SSO token for the logged-in portal contact and
     * redirect them to the requested external app (drishti | smdost).
     *
     * The token is a signed HS256 JWT verified by the target app using the
     * shared PORTAL_SSO_SECRET. It expires in 10 minutes.
     */
    public function redirect(string $app): RedirectResponse
    {
        abort_unless(in_array($app, ['drishti', 'smdost'], true), 404);

        /** @var Contact $contact */
        $contact  = auth('portal')->user();
        $customer = $contact->customer;

        if ($app === 'drishti' && ! $customer->drishti_client_id) {
            return redirect()->route('portal.home')
                ->with('error', 'Your account is not connected to Drishti yet. Please contact support.');
        }

        if ($app === 'smdost' && ! $customer->smdost_client_id) {
            return redirect()->route('portal.home')
                ->with('error', 'Your account is not connected to Social Media Dost yet. Please contact support.');
        }

        $token = PortalSsoToken::generate($contact, $customer);

        $target = match ($app) {
            'drishti' => rtrim((string) config('services.drishti.base_url'), '/') . '/api/sso?token=' . urlencode($token),
            'smdost'  => rtrim((string) config('services.smdost.base_url'), '/') . '/sso?token=' . urlencode($token),
        };

        return redirect()->away($target);
    }
}
