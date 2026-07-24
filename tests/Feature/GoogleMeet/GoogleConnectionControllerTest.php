<?php

use App\Enums\UserRole;
use App\Models\GoogleAccountConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
        'services.google_meet.redirect_uri' => 'https://crm.niranjanenterprises.co.in/settings/google/callback',
    ]);
});

it('redirects to Google when starting the connect flow', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    $response = $this->actingAs($user)->get(route('google.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com');
});

it('404s the connect routes entirely when the feature flag is off', function () {
    config(['services.google_meet.enabled' => false]);
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)->get(route('google.redirect'))->assertNotFound();
});

it('completes the callback and stores a connection on a valid code+state', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 3600]),
        'www.googleapis.com/oauth2/v2/userinfo' => Http::response(['email' => 'rep@niranjanenterprises.com']),
    ]);

    // Prime the session state the redirect step would have set.
    $this->actingAs($user)->withSession(['google_oauth_state' => 'abc123'])
        ->get(route('google.callback', ['code' => 'real-code', 'state' => 'abc123']))
        ->assertRedirect(route('profile.edit'));

    expect(GoogleAccountConnection::where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects a callback with a mismatched state (CSRF protection)', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)->withSession(['google_oauth_state' => 'expected'])
        ->get(route('google.callback', ['code' => 'real-code', 'state' => 'wrong']))
        ->assertRedirect(route('profile.edit'));

    expect(GoogleAccountConnection::count())->toBe(0);
});

it('disconnects a user\'s Google account', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('google.disconnect'))->assertRedirect(route('profile.edit'));

    expect(GoogleAccountConnection::where('user_id', $user->id)->exists())->toBeFalse();
});

it('shows the Connect Google Account section on the profile page when enabled', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Connect Google Account');
});

it('hides the Google Account section entirely when the feature flag is off', function () {
    config(['services.google_meet.enabled' => false]);
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Connect Google Account');
});

it('shows the connected email and a disconnect button once connected', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'google_email' => 'rep@niranjanenterprises.com']);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('rep@niranjanenterprises.com')
        ->assertSee('Disconnect');
});
