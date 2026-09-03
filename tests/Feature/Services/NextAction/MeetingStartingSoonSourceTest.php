<?php

use App\Enums\MeetingParticipantStatus;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextAction\MeetingStartingSoonSource;

function meetingStartingSoonSource(): MeetingStartingSoonSource
{
    return app(MeetingStartingSoonSource::class);
}

it('prompts the organizer when their own meeting starts within the lookahead window', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'title' => 'NEDS <> ADTA Group',
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->addMinutes(5),
    ]);

    $action = meetingStartingSoonSource()->next($user);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($meeting->id);
    expect($action->title)->toBe('Join: NEDS <> ADTA Group');
    expect($action->actionUrl)->toBe('https://meet.google.com/abc-defg-hij');
    expect($action->external)->toBeTrue();
});

it('prompts an invited (non-declined) participant too', function () {
    $organizer = User::factory()->role(UserRole::Sales)->create();
    $participant = User::factory()->role(UserRole::Support)->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $organizer->id,
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->addMinutes(5),
    ]);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $participant->id, 'status' => MeetingParticipantStatus::Accepted]);

    expect(meetingStartingSoonSource()->next($participant)?->subjectId)->toBe($meeting->id);
});

it('does not prompt a participant who declined', function () {
    $organizer = User::factory()->role(UserRole::Sales)->create();
    $participant = User::factory()->role(UserRole::Support)->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $organizer->id,
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->addMinutes(5),
    ]);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $participant->id, 'status' => MeetingParticipantStatus::Declined]);

    expect(meetingStartingSoonSource()->next($participant))->toBeNull();
});

it('does not prompt an uninvited bystander', function () {
    $organizer = User::factory()->role(UserRole::Sales)->create();
    $bystander = User::factory()->role(UserRole::Support)->create();
    Meeting::factory()->create([
        'user_id' => $organizer->id,
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->addMinutes(5),
    ]);

    expect(meetingStartingSoonSource()->next($bystander))->toBeNull();
});

it('ignores a meeting too far in the future', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Meeting::factory()->create([
        'user_id' => $user->id,
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->addMinutes(30),
    ]);

    expect(meetingStartingSoonSource()->next($user))->toBeNull();
});

it('still prompts shortly after the meeting has started, within the grace window', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Meeting::factory()->create([
        'user_id' => $user->id,
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->subMinutes(3),
    ]);

    expect(meetingStartingSoonSource()->next($user))->not->toBeNull();
});

it('stops prompting once well past the meeting start', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Meeting::factory()->create([
        'user_id' => $user->id,
        'meet_link' => 'https://meet.google.com/abc-defg-hij',
        'occurred_at' => now()->subMinutes(20),
    ]);

    expect(meetingStartingSoonSource()->next($user))->toBeNull();
});

it('ignores a meeting with no meet link (a manually-logged past meeting)', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Meeting::factory()->create([
        'user_id' => $user->id,
        'meet_link' => null,
        'occurred_at' => now()->addMinutes(5),
    ]);

    expect(meetingStartingSoonSource()->next($user))->toBeNull();
});

it('picks the soonest meeting when more than one is in the window', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    Meeting::factory()->create(['user_id' => $user->id, 'meet_link' => 'https://meet.google.com/later', 'occurred_at' => now()->addMinutes(9)]);
    $sooner = Meeting::factory()->create(['user_id' => $user->id, 'meet_link' => 'https://meet.google.com/sooner', 'occurred_at' => now()->addMinutes(2)]);

    expect(meetingStartingSoonSource()->next($user)?->subjectId)->toBe($sooner->id);
});

it('excludes a snoozed meeting but includes it again once the snooze expires', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'meet_link' => 'https://meet.google.com/abc', 'occurred_at' => now()->addMinutes(5)]);

    NextActionSnooze::create([
        'user_id' => $user->id,
        'source_key' => 'meeting_starting_soon',
        'subject_type' => Meeting::class,
        'subject_id' => $meeting->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(meetingStartingSoonSource()->next($user))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(meetingStartingSoonSource()->next($user)?->subjectId)->toBe($meeting->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'meet_link' => 'https://meet.google.com/abc', 'occurred_at' => now()->addMinutes(5)]);

    meetingStartingSoonSource()->complete($user, $meeting->id);
})->throws(RuntimeException::class);
