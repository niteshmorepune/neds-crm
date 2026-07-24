<?php

use App\Enums\UserRole;
use App\Models\GoogleAccountConnection;
use App\Models\User;
use App\Services\GoogleOAuthClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
        'services.google_meet.redirect_uri' => 'https://crm.example.com/settings/google/callback',
    ]);
});

it('builds an authorize URL with the right scopes, client id, and state', function () {
    $url = app(GoogleOAuthClient::class)->authorizeUrl('random-state-123');

    expect($url)->toContain('accounts.google.com/o/oauth2/v2/auth')
        ->toContain('client_id=test-client-id')
        ->toContain('access_type=offline')
        ->toContain('prompt=consent')
        ->toContain('state=random-state-123')
        ->toContain(urlencode('calendar.readonly'))
        ->toContain(urlencode('drive.readonly'));
});

it('exchanges an authorization code for tokens and stores the connection', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'real-access-token',
            'refresh_token' => 'real-refresh-token',
            'expires_in' => 3600,
        ]),
        'www.googleapis.com/oauth2/v2/userinfo' => Http::response(['email' => 'rep@niranjanenterprises.com']),
    ]);

    $connection = app(GoogleOAuthClient::class)->connect($user, 'auth-code-abc');

    expect($connection)->not->toBeNull()
        ->and($connection->user_id)->toBe($user->id)
        ->and($connection->access_token)->toBe('real-access-token')
        ->and($connection->refresh_token)->toBe('real-refresh-token')
        ->and($connection->google_email)->toBe('rep@niranjanenterprises.com');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/token')
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'auth-code-abc');
});

it('returns null and does not store anything when the token exchange fails', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Http::fake(['oauth2.googleapis.com/token' => Http::response('bad request', 400)]);

    $connection = app(GoogleOAuthClient::class)->connect($user, 'auth-code-abc');

    expect($connection)->toBeNull();
    expect(GoogleAccountConnection::count())->toBe(0);
});

it('returns null when Google omits a refresh token (re-consent needed)', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-only', 'expires_in' => 3600])]);

    $connection = app(GoogleOAuthClient::class)->connect($user, 'auth-code-abc');

    expect($connection)->toBeNull();
});

it('refreshes an expired access token and persists it', function () {
    $connection = GoogleAccountConnection::factory()->create([
        'access_token' => 'old-token',
        'expires_at' => now()->subMinute(),
    ]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600]),
    ]);

    $result = app(GoogleOAuthClient::class)->ensureFreshToken($connection);

    expect($result)->toBeTrue()
        ->and($connection->fresh()->access_token)->toBe('new-token');
});

it('does not call Google when the token is not yet expired', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake();

    $result = app(GoogleOAuthClient::class)->ensureFreshToken($connection);

    expect($result)->toBeTrue();
    Http::assertNothingSent();
});

it('returns false when the refresh call fails', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->subMinute()]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response('error', 401)]);

    expect(app(GoogleOAuthClient::class)->ensureFreshToken($connection))->toBeFalse();
});
