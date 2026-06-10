<?php

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\MenuItem;
use App\Models\User;

it('logs creation with the acting user and subject', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin);

    $item = MenuItem::create([
        'key' => 'temp',
        'label' => 'Temp',
        'route' => 'dashboard',
        'icon' => 'dot',
        'sort_order' => 99,
    ]);

    $activity = Activity::where('subject_type', MenuItem::class)
        ->where('subject_id', $item->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBe($admin->id)
        ->and($activity->changes)->toHaveKey('key');
});

it('logs updates with only the changed fields and ignores timestamps', function () {
    $item = MenuItem::create([
        'key' => 'temp',
        'label' => 'Temp',
        'route' => 'dashboard',
        'icon' => 'dot',
        'sort_order' => 99,
    ]);

    $item->update(['label' => 'Renamed']);

    $activity = Activity::where('subject_type', MenuItem::class)
        ->where('subject_id', $item->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->changes)->toHaveKey('label')
        ->and($activity->changes)->not->toHaveKey('updated_at');
});

it('does not log an update when nothing meaningful changed', function () {
    $item = MenuItem::create([
        'key' => 'temp',
        'label' => 'Temp',
        'route' => 'dashboard',
        'icon' => 'dot',
        'sort_order' => 99,
    ]);

    $before = Activity::where('event', 'updated')->count();

    // Saving identical attributes produces no dirty changes.
    $item->update(['label' => 'Temp']);

    expect(Activity::where('event', 'updated')->count())->toBe($before);
});
