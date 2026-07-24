<?php

namespace App\Services;

use App\Models\GoogleAccountConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plain OAuth2 authorization-code flow against Google, via Laravel's HTTP
 * client — deliberately no google/apiclient SDK, same Hostinger-safe
 * precedent as GoogleSpeechClient. Per-user OAuth (each staff member
 * connects their own account), not domain-wide delegation.
 */
class GoogleOAuthClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /** Read-only scopes only — this feature never writes to a user's Calendar or Drive. */
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.readonly',
        'https://www.googleapis.com/auth/drive.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
    ];

    public function authorizeUrl(string $state): string
    {
        $params = [
            'client_id' => config('services.google_meet.client_id'),
            'redirect_uri' => config('services.google_meet.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            // Forces Google to always return a refresh_token, even for a user
            // re-connecting after already having granted consent once before.
            'prompt' => 'consent',
            'state' => $state,
        ];

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    /**
     * Exchanges an authorization code for tokens and stores the connection.
     * Returns null (never throws) on any failure — the caller shows a
     * friendly "couldn't connect, try again" message.
     */
    public function connect(User $user, string $code): ?GoogleAccountConnection
    {
        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => config('services.google_meet.client_id'),
                'client_secret' => config('services.google_meet.client_secret'),
                'redirect_uri' => config('services.google_meet.redirect_uri'),
                'grant_type' => 'authorization_code',
            ]);

            if (! $response->successful()) {
                Log::warning('Google OAuth code exchange failed', ['status' => $response->status()]);

                return null;
            }

            $accessToken = $response->json('access_token');
            $refreshToken = $response->json('refresh_token');

            if (! $accessToken || ! $refreshToken) {
                // No refresh_token usually means the user has connected before
                // without `prompt=consent` taking effect — asking them to
                // reconnect is the only real recovery.
                Log::warning('Google OAuth exchange missing access/refresh token', ['user_id' => $user->id]);

                return null;
            }

            return GoogleAccountConnection::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
                    'scopes' => implode(' ', self::SCOPES),
                    'google_email' => $this->fetchEmail($accessToken),
                    'connected_at' => now(),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Google OAuth connect exception', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Refreshes and persists a connection's access token if it's expired. Returns false on failure. */
    public function ensureFreshToken(GoogleAccountConnection $connection): bool
    {
        if (! $connection->isExpired()) {
            return true;
        }

        try {
            $response = Http::asForm()->post(self::TOKEN_URL, [
                'refresh_token' => $connection->refresh_token,
                'client_id' => config('services.google_meet.client_id'),
                'client_secret' => config('services.google_meet.client_secret'),
                'grant_type' => 'refresh_token',
            ]);

            if (! $response->successful()) {
                Log::warning('Google OAuth token refresh failed', ['user_id' => $connection->user_id, 'status' => $response->status()]);

                return false;
            }

            $connection->update([
                'access_token' => $response->json('access_token'),
                'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Google OAuth token refresh exception', ['user_id' => $connection->user_id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function fetchEmail(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)->get(self::USERINFO_URL);

            return $response->successful() ? $response->json('email') : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
