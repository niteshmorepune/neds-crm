<?php

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a manager view, but forbids a sales user, from the notice board management screen', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('announcements.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('announcements.index'))->assertForbidden();
});

it('renders the create and edit pages for an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $announcement = Announcement::factory()->create();

    $this->actingAs($admin)->get(route('announcements.create'))->assertOk();
    $this->actingAs($admin)->get(route('announcements.edit', $announcement))->assertOk();
});

it('posts a new announcement and stamps the creating user', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('announcements.store'), [
        'title' => 'Independence Day Holiday',
        'body' => 'The office will be closed tomorrow, 15 Aug, for Independence Day.',
        'audience' => 'both',
        'starts_at' => now()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ])->assertRedirect(route('announcements.index'));

    $announcement = Announcement::firstWhere('title', 'Independence Day Holiday');
    expect($announcement)->not->toBeNull()
        ->and($announcement->created_by)->toBe($admin->id)
        ->and($announcement->is_pinned)->toBeFalse(); // checkbox not sent on the add form
});

it('rejects a bad audience value', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('announcements.store'), [
        'title' => 'Bad',
        'body' => 'Bad audience',
        'audience' => 'everyone',
        'starts_at' => now()->toDateTimeString(),
    ])->assertSessionHasErrors('audience');
});

it('rejects an end date before the start date', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('announcements.store'), [
        'title' => 'Bad window',
        'body' => 'Ends before it starts',
        'audience' => 'staff',
        'starts_at' => now()->toDateTimeString(),
        'ends_at' => now()->subDay()->toDateTimeString(),
    ])->assertSessionHasErrors('ends_at');
});

it('updates an announcement', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $announcement = Announcement::factory()->create(['title' => 'Old title']);

    $this->actingAs($admin)->put(route('announcements.update', $announcement), [
        'title' => 'New title',
        'body' => $announcement->body,
        'audience' => $announcement->audience->value,
        'is_pinned' => '1',
        'starts_at' => $announcement->starts_at->format('Y-m-d H:i:s'),
        'ends_at' => $announcement->ends_at?->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('announcements.index'));

    $announcement->refresh();
    expect($announcement->title)->toBe('New title')
        ->and($announcement->is_pinned)->toBeTrue();
});

it('deletes an announcement', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $announcement = Announcement::factory()->create();

    $this->actingAs($admin)->delete(route('announcements.destroy', $announcement))->assertRedirect();

    expect(Announcement::find($announcement->id))->toBeNull();
});

it('forbids a sales user from posting, editing or deleting an announcement', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $announcement = Announcement::factory()->create();

    $this->actingAs($sales)->post(route('announcements.store'), ['title' => 'Sneaky'])->assertForbidden();
    $this->actingAs($sales)->put(route('announcements.update', $announcement), ['title' => 'Sneaky'])->assertForbidden();
    $this->actingAs($sales)->delete(route('announcements.destroy', $announcement))->assertForbidden();
});
