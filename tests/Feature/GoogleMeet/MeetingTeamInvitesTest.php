<?php

use App\Enums\MeetingParticipantStatus;
use App\Enums\UserRole;
use App\Livewire\MeetingImport;
use App\Livewire\MyMeetingInvitations;
use App\Models\Customer;
use App\Models\GoogleAccountConnection;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\MeetingInvitation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
    ]);
});

function companyGoogleConnectionForInvites(): GoogleAccountConnection
{
    $admin = User::factory()->role(UserRole::Admin)->create();

    return GoogleAccountConnection::factory()->create(['user_id' => $admin->id, 'expires_at' => now()->addHour()]);
}

it('invites selected team members alongside the client, persists the meet link, and notifies them', function () {
    Notification::fake();
    companyGoogleConnectionForInvites();
    $organiser = User::factory()->role(UserRole::Sales)->create();
    $mohit = User::factory()->role(UserRole::Support)->create(['name' => 'Mohit Patil', 'email' => 'mohit@neds.test']);
    $customer = Customer::factory()->create(['email' => 'client@example.com']);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'evt-team-1',
            'hangoutLink' => 'https://meet.google.com/team-invite',
        ]),
    ]);

    Livewire::actingAs($organiser)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openScheduler')
        ->set('scheduleAt', now()->addHour()->format('Y-m-d\TH:i'))
        ->set('selectedTeamMemberIds', [$mohit->id])
        ->call('createMeeting');

    $meeting = Meeting::where('google_event_id', 'evt-team-1')->first();
    expect($meeting)->not->toBeNull()
        ->and($meeting->meet_link)->toBe('https://meet.google.com/team-invite')
        ->and($meeting->attendees)->toBe(['client@example.com', 'mohit@neds.test']);

    $participant = MeetingParticipant::where('meeting_id', $meeting->id)->where('user_id', $mohit->id)->first();
    expect($participant)->not->toBeNull()
        ->and($participant->status)->toBe(MeetingParticipantStatus::Pending);

    Notification::assertSentTo($mohit, MeetingInvitation::class, fn ($n) => $n->meeting->is($meeting));
});

it('does not invite or notify anyone when no team members are selected', function () {
    Notification::fake();
    companyGoogleConnectionForInvites();
    $organiser = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['email' => 'client@example.com']);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'evt-solo-1', 'hangoutLink' => 'https://meet.google.com/solo',
        ]),
    ]);

    Livewire::actingAs($organiser)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openScheduler')
        ->set('scheduleAt', now()->addHour()->format('Y-m-d\TH:i'))
        ->call('createMeeting');

    $meeting = Meeting::where('google_event_id', 'evt-solo-1')->first();
    expect($meeting->participants)->toHaveCount(0);
    Notification::assertNothingSent();
});

it('excludes the organiser from their own inviteable team members list', function () {
    companyGoogleConnectionForInvites();
    $organiser = User::factory()->role(UserRole::Sales)->create(['name' => 'Organiser Self']);
    $other = User::factory()->role(UserRole::Support)->create(['name' => 'Someone Else']);
    $inactive = User::factory()->role(UserRole::Support)->create(['name' => 'Inactive Person', 'is_active' => false]);
    $customer = Customer::factory()->create();

    Livewire::actingAs($organiser)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openScheduler')
        ->assertSee('Someone Else')
        ->assertDontSee('Organiser Self')
        ->assertDontSee('Inactive Person');
});

it('shows an invited team members participation status on the meeting row', function () {
    $customer = Customer::factory()->create();
    $organiser = User::factory()->create();
    $mohit = User::factory()->create(['name' => 'Mohit Patil']);
    $meeting = $customer->meetings()->create([
        'user_id' => $organiser->id, 'google_event_id' => 'evt-status-1',
        'title' => 'NEDS <> Client', 'occurred_at' => now()->addDay(),
    ]);
    $meeting->participants()->create(['user_id' => $mohit->id, 'status' => MeetingParticipantStatus::Accepted->value]);

    Livewire::actingAs($organiser)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->assertSee('Mohit Patil')
        ->assertSee('Accepted');
});

it('lets an invited team member accept a meeting invitation from their own dashboard widget', function () {
    $customer = Customer::factory()->create();
    $organiser = User::factory()->create();
    $invitee = User::factory()->create();
    $meeting = $customer->meetings()->create([
        'user_id' => $organiser->id, 'google_event_id' => 'evt-accept-1',
        'title' => 'NEDS <> Client', 'occurred_at' => now()->addDay(),
    ]);
    $participant = $meeting->participants()->create(['user_id' => $invitee->id]);

    Livewire::actingAs($invitee)
        ->test(MyMeetingInvitations::class)
        ->assertSee('NEDS <> Client')
        ->call('respond', $participant->id, 'accepted');

    expect($participant->fresh()->status)->toBe(MeetingParticipantStatus::Accepted);
});

it('does not let a user respond to another users meeting invitation', function () {
    $customer = Customer::factory()->create();
    $organiser = User::factory()->create();
    $invitee = User::factory()->create();
    $outsider = User::factory()->create();
    $meeting = $customer->meetings()->create([
        'user_id' => $organiser->id, 'google_event_id' => 'evt-outsider-1',
        'title' => 'NEDS <> Client', 'occurred_at' => now()->addDay(),
    ]);
    $participant = $meeting->participants()->create(['user_id' => $invitee->id]);

    Livewire::actingAs($outsider)
        ->test(MyMeetingInvitations::class)
        ->call('respond', $participant->id, 'accepted')
        ->assertStatus(403);

    expect($participant->fresh()->status)->toBe(MeetingParticipantStatus::Pending);
});

it('rejects an attempt to set a participation status back to pending', function () {
    $customer = Customer::factory()->create();
    $organiser = User::factory()->create();
    $invitee = User::factory()->create();
    $meeting = $customer->meetings()->create([
        'user_id' => $organiser->id, 'google_event_id' => 'evt-invalid-status-1',
        'title' => 'NEDS <> Client', 'occurred_at' => now()->addDay(),
    ]);
    $participant = $meeting->participants()->create(['user_id' => $invitee->id, 'status' => MeetingParticipantStatus::Accepted->value]);

    Livewire::actingAs($invitee)
        ->test(MyMeetingInvitations::class)
        ->call('respond', $participant->id, 'pending')
        ->assertStatus(422);

    expect($participant->fresh()->status)->toBe(MeetingParticipantStatus::Accepted);
});

it('only shows a users own upcoming meeting invitations, not past ones or someone elses', function () {
    $customer = Customer::factory()->create();
    $organiser = User::factory()->create();
    $invitee = User::factory()->create();
    $someoneElse = User::factory()->create();

    $upcoming = $customer->meetings()->create([
        'user_id' => $organiser->id, 'google_event_id' => 'evt-upcoming-1',
        'title' => 'Upcoming meeting', 'occurred_at' => now()->addDay(),
    ]);
    $upcoming->participants()->create(['user_id' => $invitee->id]);

    $past = $customer->meetings()->create([
        'user_id' => $organiser->id, 'google_event_id' => 'evt-past-1',
        'title' => 'Past meeting', 'occurred_at' => now()->subDay(),
    ]);
    $past->participants()->create(['user_id' => $invitee->id]);

    $othersMeeting = $customer->meetings()->create([
        'user_id' => $organiser->id, 'google_event_id' => 'evt-others-1',
        'title' => 'Someone elses meeting', 'occurred_at' => now()->addDay(),
    ]);
    $othersMeeting->participants()->create(['user_id' => $someoneElse->id]);

    Livewire::actingAs($invitee)->test(MyMeetingInvitations::class)
        ->assertSee('Upcoming meeting')
        ->assertDontSee('Past meeting')
        ->assertDontSee('Someone elses meeting');
});
