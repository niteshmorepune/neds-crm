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

it('redirects to Google when an admin starts the connect flow', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $response = $this->actingAs($admin)->get(route('google.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com');
});

it('forbids a non-admin from starting the connect flow', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('google.redirect'))->assertForbidden();
});

it('404s the connect routes entirely when the feature flag is off, even for an admin', function () {
    config(['services.google_meet.enabled' => false]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('google.redirect'))->assertNotFound();
});

it('completes the callback and stores a connection on a valid code+state', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 3600]),
        'www.googleapis.com/oauth2/v2/userinfo' => Http::response(['email' => 'rep@niranjanenterprises.com']),
    ]);

    // Prime the session state the redirect step would have set.
    $this->actingAs($admin)->withSession(['google_oauth_state' => 'abc123'])
        ->get(route('google.callback', ['code' => 'real-code', 'state' => 'abc123']))
        ->assertRedirect(route('profile.edit'));

    expect(GoogleAccountConnection::where('user_id', $admin->id)->exists())->toBeTrue();
});

it('forbids a non-admin from completing the callback', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->withSession(['google_oauth_state' => 'abc123'])
        ->get(route('google.callback', ['code' => 'real-code', 'state' => 'abc123']))
        ->assertForbidden();

    expect(GoogleAccountConnection::count())->toBe(0);
});

it('rejects a callback with a mismatched state (CSRF protection)', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->withSession(['google_oauth_state' => 'expected'])
        ->get(route('google.callback', ['code' => 'real-code', 'state' => 'wrong']))
        ->assertRedirect(route('profile.edit'));

    expect(GoogleAccountConnection::count())->toBe(0);
});

it('lets an admin disconnect the company Google account', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->delete(route('google.disconnect'))->assertRedirect(route('profile.edit'));

    expect(GoogleAccountConnection::where('user_id', $admin->id)->exists())->toBeFalse();
});

it('forbids a non-admin from disconnecting', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherAdmin = User::factory()->role(UserRole::Admin)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $otherAdmin->id]);

    $this->actingAs($sales)->delete(route('google.disconnect'))->assertForbidden();

    expect(GoogleAccountConnection::where('user_id', $otherAdmin->id)->exists())->toBeTrue();
});

it('disconnect removes the company connection even if a DIFFERENT admin originally connected it', function () {
    $connectingAdmin = User::factory()->role(UserRole::Admin)->create();
    $disconnectingAdmin = User::factory()->role(UserRole::Admin)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $connectingAdmin->id, 'connected_at' => now()]);

    $this->actingAs($disconnectingAdmin)->delete(route('google.disconnect'))->assertRedirect(route('profile.edit'));

    expect(GoogleAccountConnection::where('user_id', $connectingAdmin->id)->exists())->toBeFalse();
});

it('shows the Connect Google Account section on the profile page for an admin when enabled', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Connect Google Account');
});

it('hides the Google Account section entirely for a non-admin', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Google Account');
});

it('hides the Google Account section entirely when the feature flag is off', function () {
    config(['services.google_meet.enabled' => false]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Connect Google Account');
});

it('shows the connected email and a disconnect button to an admin once connected', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $admin->id, 'google_email' => 'rep@niranjanenterprises.com']);

    $this->actingAs($admin)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('rep@niranjanenterprises.com')
        ->assertSee('Disconnect');
});

it('shows the company connection as connected to a SECOND admin who never connected it themselves', function () {
    $connectingAdmin = User::factory()->role(UserRole::Admin)->create();
    $viewingAdmin = User::factory()->role(UserRole::Admin)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $connectingAdmin->id, 'google_email' => 'neds.crm@gmail.com']);

    $this->actingAs($viewingAdmin)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('neds.crm@gmail.com')
        ->assertSee('Disconnect');
});
