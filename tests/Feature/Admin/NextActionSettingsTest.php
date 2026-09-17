<?php

use App\Enums\UserRole;
use App\Models\NextActionSetting;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a manager view the Notification Settings page but forbids a sales user', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('next-action-settings.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('next-action-settings.index'))->assertForbidden();
});

it('lets an admin view the Notification Settings page', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get(route('next-action-settings.index'))->assertOk();
});

it('defaults the Next Action pop-up to not-paused the first time it is read', function () {
    expect(NextActionSetting::current()->paused)->toBeFalse();
});

it('lets a manager pause the pop-up for everyone, recording who and when', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->post(route('next-action-settings.pause'))->assertRedirect();

    $setting = NextActionSetting::current();
    expect($setting->paused)->toBeTrue()
        ->and($setting->updated_by)->toBe($manager->id);
});

it('lets a manager resume the pop-up after pausing it', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    NextActionSetting::current()->update(['paused' => true, 'updated_by' => $manager->id]);

    $this->actingAs($manager)->post(route('next-action-settings.resume'))->assertRedirect();

    expect(NextActionSetting::current()->paused)->toBeFalse();
});

it('forbids a sales user from pausing or resuming', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->post(route('next-action-settings.pause'))->assertForbidden();
    $this->actingAs($sales)->post(route('next-action-settings.resume'))->assertForbidden();

    expect(NextActionSetting::current()->paused)->toBeFalse();
});
