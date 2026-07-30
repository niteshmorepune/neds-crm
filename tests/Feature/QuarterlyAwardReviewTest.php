<?php

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Livewire\QuarterlyAwardReview;
use App\Models\Announcement;
use App\Models\QuarterlyAward;
use App\Models\User;
use App\Notifications\QuarterlyAwardNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('approves an award as-is: notifies the winner and posts a Notice Board announcement', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $winner = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    $award = QuarterlyAward::factory()->create([
        'user_id' => $winner->id,
        'status' => AwardStatus::Pending,
        'citation' => 'Alice led the team this quarter.',
    ]);

    Livewire::actingAs($manager)
        ->test(QuarterlyAwardReview::class, ['pendingAwards' => collect([$award])])
        ->call('approve', $award->id);

    $award->refresh();
    expect($award->status)->toBe(AwardStatus::Approved)
        ->and($award->reviewed_by)->toBe($manager->id)
        ->and($award->user_id)->toBe($winner->id)
        ->and($award->announcement_id)->not->toBeNull()
        ->and($award->notified_at)->not->toBeNull();

    expect(Announcement::find($award->announcement_id))
        ->not->toBeNull()
        ->body->toBe('Alice led the team this quarter.');

    Notification::assertSentTo($winner, QuarterlyAwardNotification::class);
});

it('lets the reviewer override the winner to another eligible peer', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $original = User::factory()->role(UserRole::Sales)->create();
    $override = User::factory()->role(UserRole::Sales)->create();
    $award = QuarterlyAward::factory()->create(['user_id' => $original->id, 'status' => AwardStatus::Pending]);

    Livewire::actingAs($manager)
        ->test(QuarterlyAwardReview::class, ['pendingAwards' => collect([$award])])
        ->set("forms.{$award->id}.user_id", $override->id)
        ->call('approve', $award->id);

    expect($award->fresh()->user_id)->toBe($override->id);
    Notification::assertSentTo($override, QuarterlyAwardNotification::class);
    Notification::assertNotSentTo($original, QuarterlyAwardNotification::class);
});

it('rejects an override to someone outside the eligible department', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $winner = User::factory()->role(UserRole::Sales)->create();
    $wrongDept = User::factory()->role(UserRole::Support)->create();
    $award = QuarterlyAward::factory()->create(['user_id' => $winner->id, 'status' => AwardStatus::Pending]);

    $component = Livewire::actingAs($manager)
        ->test(QuarterlyAwardReview::class, ['pendingAwards' => collect([$award])])
        ->set("forms.{$award->id}.user_id", $wrongDept->id)
        ->call('approve', $award->id);

    expect($award->fresh()->status)->toBe(AwardStatus::Pending);
    expect($component->get('error'))->not->toBeNull();
});

it('rejects an award: no notification, no announcement, certificate stays unavailable', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $winner = User::factory()->role(UserRole::Sales)->create();
    $award = QuarterlyAward::factory()->create(['user_id' => $winner->id, 'status' => AwardStatus::Pending]);

    Livewire::actingAs($manager)
        ->test(QuarterlyAwardReview::class, ['pendingAwards' => collect([$award])])
        ->call('reject', $award->id);

    $award->refresh();
    expect($award->status)->toBe(AwardStatus::Rejected)
        ->and($award->announcement_id)->toBeNull();

    Notification::assertNothingSent();
    expect($winner->can('downloadCertificate', $award))->toBeFalse();
});

it('forbids a non-manager from approving or rejecting', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $award = QuarterlyAward::factory()->create(['status' => AwardStatus::Pending]);

    Livewire::actingAs($sales)
        ->test(QuarterlyAwardReview::class, ['pendingAwards' => collect([$award])])
        ->call('approve', $award->id)
        ->assertForbidden();
});
