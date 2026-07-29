<?php

use App\Enums\MeetingSummaryStatus;
use App\Enums\UserRole;
use App\Jobs\SummarizeMeeting;
use App\Livewire\MeetingImport;
use App\Models\Customer;
use App\Models\GoogleAccountConnection;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
    ]);
});

/** The company connection is always under an Admin — a Sales/Support rep uses it, never owns one. */
function companyGoogleConnection(): GoogleAccountConnection
{
    $admin = User::factory()->role(UserRole::Admin)->create();

    return GoogleAccountConnection::factory()->create(['user_id' => $admin->id, 'expires_at' => now()->addHour()]);
}

it('prompts to ask an admin to connect Google when no company connection exists yet', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->assertSee('Ask an admin to connect', false);
});

it('a Sales rep with no Google connection of their own can still load events, via the company connection', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'items' => [[
                'id' => 'evt-1',
                'summary' => 'Client sync call',
                'start' => ['dateTime' => now()->subDay()->toRfc3339String()],
                'conferenceData' => ['conferenceId' => 'abc-defg'],
            ]],
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('loadEvents')
        ->assertSet('showPicker', true)
        ->assertSee('Client sync call');
});

it('imports a picked event into a Meeting attached to the record, crediting the IMPORTING user not the connection owner', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
            'end' => ['dateTime' => '2026-07-20T10:30:00+05:30'],
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    $meeting = Meeting::where('google_event_id', 'evt-1')->first();
    expect($meeting)->not->toBeNull()
        ->and($meeting->meetable_type)->toBe(Customer::class)
        ->and($meeting->meetable_id)->toBe($customer->id)
        ->and($meeting->user_id)->toBe($sales->id)
        ->and($meeting->title)->toBe('Client sync call');
});

it('refuses to import the same Google event twice', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    Meeting::factory()->for($customer, 'meetable')->create(['google_event_id' => 'evt-1']);

    Http::fake();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1')
        ->assertSet('error', 'This meeting has already been imported.');

    expect(Meeting::where('google_event_id', 'evt-1')->count())->toBe(1);
    Http::assertNothingSent();
});

it('blocks loadEvents/importEvent/createMeeting for a user without manage permission', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => false])
        ->call('loadEvents')
        ->assertForbidden();
});

it('attaches to a Lead as well as a Customer', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Prospect call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $lead, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    $meeting = Meeting::where('google_event_id', 'evt-1')->first();
    expect($meeting->meetable_type)->toBe(Lead::class)
        ->and($meeting->meetable_id)->toBe($lead->id);
});

it('queues a summary job after importing a meeting with a transcript, when summaries are enabled', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Queue::fake();
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
            'attachments' => [
                ['mimeType' => 'application/vnd.google-apps.document', 'fileUrl' => 'https://docs.google.com/transcript', 'fileId' => 'doc-123'],
            ],
        ]),
        'www.googleapis.com/drive/v3/files/doc-123/export*' => Http::response('Rep: hello'),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    $meeting = Meeting::where('google_event_id', 'evt-1')->first();
    expect($meeting->ai_summary_status)->toBe(MeetingSummaryStatus::Pending);
    Queue::assertPushed(SummarizeMeeting::class, fn ($job) => $job->meetingId === $meeting->id);
});

it('does not queue a summary job when the imported meeting has no transcript yet', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Queue::fake();
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    Queue::assertNothingPushed();
});

it('does not queue a summary job when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Queue::fake();
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
            'attachments' => [
                ['mimeType' => 'application/vnd.google-apps.document', 'fileUrl' => 'https://docs.google.com/transcript', 'fileId' => 'doc-123'],
            ],
        ]),
        'www.googleapis.com/drive/v3/files/doc-123/export*' => Http::response('Rep: hello'),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    Queue::assertNothingPushed();
});

it('shows imported meetings on the Customer show page', function () {
    $this->seed(MenuItemsSeeder::class);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    Meeting::factory()->for($customer, 'meetable')->create(['title' => 'Quarterly review call']);

    $this->actingAs($sales)
        ->get(route('clients.show', $customer))
        ->assertOk()
        ->assertSee('Quarterly review call');
});

it('regression: a Support user sees Create Meeting on a real client page, over real HTTP', function () {
    // 2026-07-25 — reported live: Support saw no Create Meeting/Import Meet
    // Notes/connect-prompt at all on a real client page, because the
    // Livewire component's canManage prop was fed CustomerPolicy::manage()
    // (contacts management, deliberately Support-excluded) instead of a
    // check appropriate for Meet notes. Asserts the real HTTP-rendered page,
    // not just the Livewire component in isolation, since the bug was in
    // the CONTROLLER/VIEW wiring, not the component itself.
    $this->seed(MenuItemsSeeder::class);
    companyGoogleConnection();
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create();

    $this->actingAs($support)
        ->get(route('clients.show', $customer))
        ->assertOk()
        ->assertSee('Create Meeting')
        ->assertSee('Import Meet Notes');
});

it('regression: a Support user without a company connection at least sees the connect prompt, not nothing', function () {
    $this->seed(MenuItemsSeeder::class);
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create();

    $this->actingAs($support)
        ->get(route('clients.show', $customer))
        ->assertOk()
        ->assertSee('Ask an admin to connect', false);
});

// --- Create Meeting ---

it('creates a meeting via the company connection, inviting the customer\'s billing email, and attaches it immediately', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['email' => 'client@example.com']);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'new-evt-1',
            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openScheduler')
        ->set('scheduleAt', now()->addHour()->format('Y-m-d\TH:i'))
        ->call('createMeeting')
        ->assertSet('createdMeetLink', 'https://meet.google.com/abc-defg-hij');

    $meeting = Meeting::where('google_event_id', 'new-evt-1')->first();
    expect($meeting)->not->toBeNull()
        ->and($meeting->meetable_type)->toBe(Customer::class)
        ->and($meeting->meetable_id)->toBe($customer->id)
        ->and($meeting->user_id)->toBe($sales->id)
        ->and($meeting->attendees)->toBe(['client@example.com']);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'sendUpdates=all')
            && ($request->data()['attendees'][0]['email'] ?? null) === 'client@example.com';
    });
});

it('interprets the scheduled time as Asia/Kolkata (IST), not UTC — regression for the 12:00 PM becoming 5:30 PM bug', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['email' => 'client@example.com']);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'new-evt-tz',
            'hangoutLink' => 'https://meet.google.com/tz-check',
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openScheduler')
        ->set('scheduleAt', '2026-07-29T12:00') // picked as 12:00 PM IST
        ->call('createMeeting');

    // 12:00 PM IST (UTC+5:30) is 06:30 UTC — asserting the raw request body
    // catches a regression to the old bug (bare Carbon::parse() treated the
    // wall-clock string as UTC, sending 12:00 UTC = 5:30 PM IST instead).
    Http::assertSent(function ($request) {
        return $request->data()['start']['dateTime'] === '2026-07-29T06:30:00+00:00';
    });
});

it('creates a meeting for a Lead using the lead\'s own email', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['email' => 'lead@example.com']);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'new-evt-2',
            'hangoutLink' => 'https://meet.google.com/lead-link',
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $lead, 'canManage' => true])
        ->call('openScheduler')
        ->set('scheduleAt', now()->addHour()->format('Y-m-d\TH:i'))
        ->call('createMeeting');

    $meeting = Meeting::where('google_event_id', 'new-evt-2')->first();
    expect($meeting->meetable_type)->toBe(Lead::class)
        ->and($meeting->attendees)->toBe(['lead@example.com']);
});

it('shows a friendly error instead of creating a meeting when no company connection exists', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openScheduler')
        ->call('createMeeting')
        ->assertSet('error', 'No Google account connected yet — ask an admin to connect one in Settings.');

    expect(Meeting::count())->toBe(0);
});

it('blocks createMeeting for a user without manage permission', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => false])
        ->call('createMeeting')
        ->assertForbidden();
});

// --- Sync recording & transcript ---

it('syncs recording and transcript onto an already-attached meeting, keyed by its known event id', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $meeting = Meeting::factory()->for($customer, 'meetable')->create([
        'google_event_id' => 'evt-scheduled-1',
        'drive_recording_url' => null,
        'drive_transcript_url' => null,
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-scheduled-1' => Http::response([
            'summary' => 'NEDS <> Client',
            'start' => ['dateTime' => '2026-07-25T10:00:00+05:30'],
            'end' => ['dateTime' => '2026-07-25T10:30:00+05:30'],
            'attachments' => [
                ['mimeType' => 'video/mp4', 'fileUrl' => 'https://drive.google.com/recording'],
            ],
        ]),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('syncRecording', $meeting->id);

    expect($meeting->fresh()->drive_recording_url)->toBe('https://drive.google.com/recording')
        ->and($meeting->fresh()->duration_minutes)->toBe(30);
});

it('blocks syncRecording for a user without manage permission', function () {
    companyGoogleConnection();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $meeting = Meeting::factory()->for($customer, 'meetable')->create(['google_event_id' => 'evt-x']);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => false])
        ->call('syncRecording', $meeting->id)
        ->assertForbidden();
});
