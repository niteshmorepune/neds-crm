<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->google2fa = new Google2FA;
});

/** Set a user up with confirmed 2FA and return [user, secret]. */
function enrol(UserRole $role = UserRole::Sales): array
{
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $user = User::factory()->role($role)->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

it('enrols and confirms two-factor with a valid code', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect();
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();

    $code = $this->google2fa->getCurrentOtp($user->two_factor_secret);
    $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $code])->assertSessionHas('recovery_codes');

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);
});

it('rejects confirmation with an invalid code', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    $this->actingAs($user)->post(route('two-factor.enable'));

    $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('disables two-factor', function () {
    [$user] = enrol();

    $this->actingAs($user)->delete(route('two-factor.disable'))->assertRedirect();

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull();
});

it('gates a user with 2FA enabled to the challenge until verified', function () {
    [$user, $secret] = enrol();

    // No challenge passed yet → protected pages bounce to the challenge.
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));

    // Verify with the current TOTP → reach the app.
    $code = $this->google2fa->getCurrentOtp($secret);
    $this->actingAs($user)->post(route('two-factor.challenge'), ['code' => $code])
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)->withSession(['auth.two_factor_passed' => true])
        ->get(route('dashboard'))->assertOk();
});

it('accepts a recovery code and consumes it', function () {
    [$user] = enrol();

    $this->actingAs($user)->post(route('two-factor.challenge'), ['code' => 'AAAAA-BBBBB'])
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->two_factor_recovery_codes)
        ->not->toContain('AAAAA-BBBBB')
        ->toHaveCount(1);
});

it('rejects an invalid challenge code', function () {
    [$user] = enrol();

    $this->actingAs($user)->post(route('two-factor.challenge'), ['code' => '999999'])
        ->assertSessionHasErrors('code');
});

it('forces an admin without 2FA to set it up when enrollment is enforced', function () {
    config(['security.enforce_two_factor_enrollment' => true]);
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
    // The setup page itself is reachable.
    $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->assertSee('Two-Factor Authentication');
});

it('does not force a sales user to enable 2FA', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk();
});
