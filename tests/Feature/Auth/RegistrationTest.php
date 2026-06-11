<?php

use App\Models\User;

// Public registration is disabled on this internal CRM — staff accounts are
// created by an admin, not self-service.

test('the registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('registration cannot be submitted', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
