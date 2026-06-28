<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\Customer;

/**
 * Signs and verifies short-lived HS256 JWTs for cross-portal SSO.
 *
 * The same PORTAL_SSO_SECRET must be set on all three apps (CRM, Drishti,
 * SMDost). No external package is needed — HS256 is pure HMAC-SHA256.
 */
class PortalSsoToken
{
    private const TTL_SECONDS = 600; // 10 minutes

    public static function generate(Contact $contact, Customer $customer): string
    {
        $secret  = self::secret();
        $header  = self::b64url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64url((string) json_encode([
            'sub'               => (string) $contact->id,
            'email'             => $contact->email,
            'name'              => $contact->name,
            'customer_id'       => $customer->id,
            'drishti_client_id' => $customer->drishti_client_id,
            'smdost_client_id'  => $customer->smdost_client_id,
            'iat'               => now()->timestamp,
            'exp'               => now()->addSeconds(self::TTL_SECONDS)->timestamp,
        ]));
        $sig = self::b64url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        return "{$header}.{$payload}.{$sig}";
    }

    /**
     * Verify a token. Returns the claims array or null if invalid / expired.
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token): ?array
    {
        $secret = self::secret();
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $sig] = $parts;

        $expected = self::b64url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $claims = json_decode(self::b64urlDecode($payload), true);
        if (! is_array($claims) || ($claims['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return $claims;
    }

    private static function secret(): string
    {
        return (string) config('services.portal_sso.secret');
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $rem = strlen($data) % 4;
        if ($rem !== 0) {
            $data .= str_repeat('=', 4 - $rem);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
