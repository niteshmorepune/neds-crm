<?php

use App\Enums\UserRole;
use App\Mail\UserInvitation;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('does not show a password field on the create user form', function () {
    $this->actingAs($this->admin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertDontSee('id="password"', false)
        ->assertSee('Set my password');
});

it('still shows the password field on the edit user form', function () {
    $staff = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($this->admin)
        ->get(route('users.edit', $staff))
        ->assertOk()
        ->assertSee('id="password"', false);
});

it('cannot log in with the random password a new account is created with', function () {
    Mail::fake();

    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Ravi Support',
        'email' => 'ravi@neds.test',
        'role' => UserRole::Support->value,
        'is_active' => '1',
    ]);

    $user = User::firstWhere('email', 'ravi@neds.test');

    auth()->logout();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('lets a newly invited user set their password via the emailed link and log in', function () {
    Mail::fake();

    $this->actingAs($this->admin)->post(route('users.store'), [
        'name' => 'Priya Sales',
        'email' => 'priya@neds.test',
        'role' => UserRole::Sales->value,
        'is_active' => '1',
    ])->assertRedirect(route('users.index'));

    $user = User::firstWhere('email', 'priya@neds.test');

    $token = null;
    Mail::assertSent(UserInvitation::class, function (UserInvitation $mail) use ($user, &$token) {
        $token = $mail->token;

        return $mail->hasTo($user->email) && $mail->user->is($user);
    });

    auth()->logout();

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'my-new-password',
        'password_confirmation' => 'my-new-password',
    ])->assertRedirect(route('login'));

    expect(Hash::check('my-new-password', $user->refresh()->password))->toBeTrue();

    $this->post('/login', ['email' => $user->email, 'password' => 'my-new-password'])
        ->assertRedirect(route('dashboard'));
});
