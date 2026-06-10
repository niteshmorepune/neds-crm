<?php

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\MenuResolver;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->resolver = app(MenuResolver::class);
});

it('busts the per-user cache when flush() is called', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    // Prime the cache.
    $before = $this->resolver->visibleItems($admin)->firstWhere('key', 'customer');
    expect($before->route)->toBe('clients.index');

    // Change the item, then flush.
    MenuItem::where('key', 'customer')->update(['route' => 'something.else']);
    $this->resolver->flush();

    $after = $this->resolver->visibleItems($admin)->firstWhere('key', 'customer');
    expect($after->route)->toBe('something.else');
});
