<?php

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;

it('scopeActive includes a currently-running announcement', function () {
    $a = Announcement::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

    expect(Announcement::active()->pluck('id'))->toContain($a->id);
});

it('scopeActive excludes an announcement that has not started yet', function () {
    $a = Announcement::factory()->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addDay()]);

    expect(Announcement::active()->pluck('id'))->not->toContain($a->id);
});

it('scopeActive excludes an announcement that has already ended', function () {
    $a = Announcement::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subHour()]);

    expect(Announcement::active()->pluck('id'))->not->toContain($a->id);
});

it('scopeActive includes a standing announcement with no end date', function () {
    $a = Announcement::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => null]);

    expect(Announcement::active()->pluck('id'))->toContain($a->id);
});

it('scopeForStaff includes Staff and Both, excludes Clients-only', function () {
    $staff = Announcement::factory()->create(['audience' => AnnouncementAudience::Staff->value]);
    $both = Announcement::factory()->create(['audience' => AnnouncementAudience::Both->value]);
    $clients = Announcement::factory()->create(['audience' => AnnouncementAudience::Clients->value]);

    $ids = Announcement::forStaff()->pluck('id');

    expect($ids)->toContain($staff->id)->toContain($both->id)->not->toContain($clients->id);
});

it('scopeForClients includes Clients and Both, excludes Staff-only', function () {
    $staff = Announcement::factory()->create(['audience' => AnnouncementAudience::Staff->value]);
    $both = Announcement::factory()->create(['audience' => AnnouncementAudience::Both->value]);
    $clients = Announcement::factory()->create(['audience' => AnnouncementAudience::Clients->value]);

    $ids = Announcement::forClients()->pluck('id');

    expect($ids)->toContain($clients->id)->toContain($both->id)->not->toContain($staff->id);
});

it('scopeNewestFirst puts a pinned announcement ahead of a more recent unpinned one', function () {
    $recent = Announcement::factory()->create(['starts_at' => now()->subMinute(), 'is_pinned' => false]);
    $pinned = Announcement::factory()->create(['starts_at' => now()->subDay(), 'is_pinned' => true]);

    expect(Announcement::newestFirst()->pluck('id')->first())->toBe($pinned->id);
});
