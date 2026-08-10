<?php

use App\Enums\UserRole;
use App\Mail\PartnerInvitation;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Mail;

it('redirects partner portal guests to the partner login', function () {
    $this->get(route('partner-portal.home'))->assertRedirect(route('partner-portal.login'));
});

it('lets a portal-enabled partner log in', function () {
    $partner = Partner::factory()->portalUser()->create(['email' => 'partner@x.test']);

    $this->post(route('partner-portal.login'), ['email' => 'partner@x.test', 'password' => 'password'])
        ->assertRedirect(route('partner-portal.home'));

    $this->assertAuthenticatedAs($partner, 'partner');
});

it('rejects a wrong password', function () {
    Partner::factory()->portalUser()->create(['email' => 'partner@x.test']);

    $this->post(route('partner-portal.login'), ['email' => 'partner@x.test', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('partner');
});

it('refuses login for a partner without portal access even with the right password', function () {
    Partner::factory()->create([
        'email' => 'noportal@x.test',
        'password' => bcrypt('password'),
        'portal_enabled' => false,
    ]);

    $this->post(route('partner-portal.login'), ['email' => 'noportal@x.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('partner');
});

it('sets a password from a valid invitation token and signs in', function () {
    $partner = Partner::factory()->create(['email' => 'invitee@x.test']);
    $token = $partner->inviteToPortal();

    $this->get(route('partner-portal.password.setup', $token))->assertOk();

    $this->post(route('partner-portal.password.store', $token), [
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertRedirect(route('partner-portal.home'));

    $this->assertAuthenticatedAs($partner->fresh(), 'partner');
    expect($partner->fresh()->password_set_at)->not->toBeNull()
        ->and($partner->fresh()->invitation_token)->toBeNull();
});

it('shows a friendly invalid-link page for an invalid invitation token', function () {
    $this->get(route('partner-portal.password.setup', 'bogus-token'))
        ->assertOk()
        ->assertSee('This link is no longer valid');
});

it('shows a friendly invalid-link page once the token has already been used', function () {
    $partner = Partner::factory()->create(['email' => 'used@x.test']);
    $token = $partner->inviteToPortal();

    $this->post(route('partner-portal.password.store', $token), [
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertRedirect(route('partner-portal.home'));

    $this->post(route('partner-portal.logout'));

    $this->post(route('partner-portal.password.store', $token), [
        'password' => 'secret456', 'password_confirmation' => 'secret456',
    ])->assertOk()->assertSee('This link is no longer valid');
});

it('shows a friendly invalid-link page when portal access was revoked after the invite was sent', function () {
    $partner = Partner::factory()->create(['email' => 'revoked@x.test']);
    $token = $partner->inviteToPortal();
    $partner->revokePortalAccess();

    $this->get(route('partner-portal.password.setup', $token))
        ->assertOk()
        ->assertSee('This link is no longer valid');
});

it('lets an admin invite a partner to the portal and emails them', function () {
    Mail::fake();
    $this->seed(MenuItemsSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $partner = Partner::factory()->create(['email' => 'new@x.test']);

    $this->actingAs($admin)->post(route('partners.invite', $partner))->assertRedirect();

    expect($partner->fresh()->portal_enabled)->toBeTrue();
    Mail::assertSent(PartnerInvitation::class, fn (PartnerInvitation $m) => $m->hasTo('new@x.test'));
});

it('lets an admin revoke a partner\'s portal access', function () {
    $this->seed(MenuItemsSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $partner = Partner::factory()->portalUser()->create();

    $this->actingAs($admin)->post(route('partners.revoke', $partner))->assertRedirect();

    expect($partner->fresh()->hasPortalAccess())->toBeFalse();
});
