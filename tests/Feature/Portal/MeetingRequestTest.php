<?php

use App\Enums\MeetingPlatform;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\GoogleAccountConnection;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MeetingRequested;
use Illuminate\Support\Facades\Http;

function portalCompanyGoogleConnection(): GoogleAccountConnection
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    return GoogleAccountConnection::factory()->create(['user_id' => $admin->id, 'expires_at' => now()->addHour()]);
}

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Sales]);
    $this->customer = Customer::factory()->create(['owner_id' => $this->owner->id, 'email' => 'billing@example.test']);
    $this->contact = Contact::factory()->portalUser()->create(['customer_id' => $this->customer->id]);
    $this->project = Project::factory()->create(['customer_id' => $this->customer->id, 'owner_id' => $this->owner->id]);
});

it('creates a real Calendar event and marks it requested_by_client when a Google connection exists', function () {
    portalCompanyGoogleConnection();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'portal-evt-1',
            'hangoutLink' => 'https://meet.google.com/portal-req',
        ]),
    ]);

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.projects.request-meeting', $this->project), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'client_note' => 'Want to discuss the homepage redesign.',
        ])
        ->assertRedirect();

    $meeting = Meeting::where('google_event_id', 'portal-evt-1')->first();

    expect($meeting)->not->toBeNull()
        ->and($meeting->meetable_type)->toBe(Customer::class)
        ->and($meeting->meetable_id)->toBe($this->customer->id)
        ->and($meeting->user_id)->toBe($this->owner->id)
        ->and($meeting->meet_link)->toBe('https://meet.google.com/portal-req')
        ->and($meeting->platform)->toBe(MeetingPlatform::GoogleMeet)
        ->and($meeting->requested_by_client)->toBeTrue()
        ->and($meeting->client_note)->toBe('Want to discuss the homepage redesign.')
        ->and($meeting->attendees)->toContain('billing@example.test');

    expect($this->owner->fresh()->notifications()->where('type', MeetingRequested::class)->exists())->toBeTrue();
});

it('falls back to a manually-schedulable meeting when no Google connection exists', function () {
    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.projects.request-meeting', $this->project), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    $meeting = Meeting::where('requested_by_client', true)->first();

    expect($meeting)->not->toBeNull()
        ->and($meeting->meet_link)->toBeNull()
        ->and($meeting->platform)->toBe(MeetingPlatform::Other)
        ->and($meeting->google_event_id)->toStartWith('requested-')
        ->and($meeting->isGoogleMeetImport())->toBeFalse();

    expect($this->owner->fresh()->notifications()->where('type', MeetingRequested::class)->exists())->toBeTrue();
});

it('interprets the requested time as Asia/Kolkata (IST), not UTC — same regression class as the Create Meeting timezone bug', function () {
    portalCompanyGoogleConnection();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'portal-evt-tz',
            'hangoutLink' => 'https://meet.google.com/tz-check',
        ]),
    ]);

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.projects.request-meeting', $this->project), [
            'scheduled_at' => '2026-08-20T12:00',
        ]);

    Http::assertSent(function ($request) {
        return $request->data()['start']['dateTime'] === '2026-08-20T06:30:00+00:00';
    });
});

it('404s when requesting a meeting for another customer\'s project', function () {
    $theirProject = Project::factory()->create();

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.projects.request-meeting', $theirProject), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertNotFound();
});

it('rejects a request for a time in the past', function () {
    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.projects.request-meeting', $this->project), [
            'scheduled_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('scheduled_at');

    expect(Meeting::where('requested_by_client', true)->exists())->toBeFalse();
});

it('shows a graceful error when the project has no team member assigned yet', function () {
    $unassignedProject = Project::factory()->create(['customer_id' => $this->customer->id, 'owner_id' => null]);

    $this->actingAs($this->contact, 'portal')
        ->post(route('portal.projects.request-meeting', $unassignedProject), [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('scheduled_at');

    expect(Meeting::where('meetable_id', $this->customer->id)->where('requested_by_client', true)->exists())->toBeFalse();
});
