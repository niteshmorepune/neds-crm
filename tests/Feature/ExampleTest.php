<?php

use App\Enums\UserRole;
use App\Models\User;

it('redirects the root to the login page for guests', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('redirects the root to the dashboard for authenticated users', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create());

    $this->get('/')->assertRedirect(route('dashboard'));
});
