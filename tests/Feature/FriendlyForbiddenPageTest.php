<?php

use App\Enums\UserRole;
use App\Models\User;

it('shows a friendly message instead of the default 403 page', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)
        ->get(route('users.index'))
        ->assertForbidden()
        ->assertSee('You do not have permission to perform this action. Please contact your administrator if you need access.')
        ->assertDontSee('This action is unauthorized');
});
