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

it('prompts to connect Google when the user has no connection yet', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->assertSee('Connect your Google account');
});

it('loads recent Meet events into the picker once connected', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addHour()]);
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

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('loadEvents')
        ->assertSet('showPicker', true)
        ->assertSee('Client sync call');
});

it('imports a picked event into a Meeting attached to the record', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addHour()]);
    $customer = Customer::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
            'end' => ['dateTime' => '2026-07-20T10:30:00+05:30'],
        ]),
    ]);

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    $meeting = Meeting::where('google_event_id', 'evt-1')->first();
    expect($meeting)->not->toBeNull()
        ->and($meeting->meetable_type)->toBe(Customer::class)
        ->and($meeting->meetable_id)->toBe($customer->id)
        ->and($meeting->user_id)->toBe($user->id)
        ->and($meeting->title)->toBe('Client sync call');
});

it('refuses to import the same Google event twice', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addHour()]);
    $customer = Customer::factory()->create();
    Meeting::factory()->for($customer, 'meetable')->create(['google_event_id' => 'evt-1']);

    Http::fake();

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1')
        ->assertSet('error', 'This meeting has already been imported.');

    expect(Meeting::where('google_event_id', 'evt-1')->count())->toBe(1);
    Http::assertNothingSent();
});

it('blocks loadEvents/importEvent for a user without manage permission', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => false])
        ->call('loadEvents')
        ->assertForbidden();
});

it('attaches to a Lead as well as a Customer', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addHour()]);
    $lead = Lead::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Prospect call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
        ]),
    ]);

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $lead, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    $meeting = Meeting::where('google_event_id', 'evt-1')->first();
    expect($meeting->meetable_type)->toBe(Lead::class)
        ->and($meeting->meetable_id)->toBe($lead->id);
});

it('queues a summary job after importing a meeting with a transcript, when summaries are enabled', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Queue::fake();
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addHour()]);
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

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    $meeting = Meeting::where('google_event_id', 'evt-1')->first();
    expect($meeting->ai_summary_status)->toBe(MeetingSummaryStatus::Pending);
    Queue::assertPushed(SummarizeMeeting::class, fn ($job) => $job->meetingId === $meeting->id);
});

it('does not queue a summary job when the imported meeting has no transcript yet', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Queue::fake();
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addHour()]);
    $customer = Customer::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
        ]),
    ]);

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    Queue::assertNothingPushed();
});

it('does not queue a summary job when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Queue::fake();
    $user = User::factory()->role(UserRole::Sales)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addHour()]);
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

    Livewire::actingAs($user)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('importEvent', 'evt-1');

    Queue::assertNothingPushed();
});

it('shows imported meetings on the Customer show page', function () {
    $this->seed(MenuItemsSeeder::class);
    $user = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $user->id]);
    Meeting::factory()->for($customer, 'meetable')->create(['title' => 'Quarterly review call']);

    $this->actingAs($user)
        ->get(route('clients.show', $customer))
        ->assertOk()
        ->assertSee('Quarterly review call');
});
