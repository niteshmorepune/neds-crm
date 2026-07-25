<?php

namespace App\Livewire;

use App\Enums\MeetingSummaryStatus;
use App\Jobs\SummarizeMeeting;
use App\Models\Customer;
use App\Models\GoogleAccountConnection;
use App\Models\Meeting;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleMeetImportClient;
use App\Support\GoogleMeet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * "Create Meeting" + "Import Meet Notes" — embedded on Customer/Lead pages
 * (mirrors CallLog's attach scope exactly). Since 2026-07-25 both actions go
 * through one company-wide Google connection (App\Models\
 * GoogleAccountConnection::forCompany(), Admin-only to connect) rather than
 * each staff member's own — "Create Meeting" schedules a Meet-enabled
 * Calendar event on behalf of whoever's viewing this record and invites the
 * client/lead's own email; the resulting Meeting row is attached
 * immediately, so there's no later manual matching step. "Import Meet
 * Notes" still exists for calls that happened outside that flow (ad hoc, or
 * from before this change) — it now browses the COMPANY connection's recent
 * events rather than the viewing user's own.
 *
 * "Sync recording & transcript" reuses the same fetchEventDetail() call,
 * keyed by the google_event_id already stored on a Meeting row, to fill in
 * the recording/transcript once Google's finished processing it — then (as
 * before) queues a Claude summary if a transcript came back and summaries
 * are enabled. No dedicated Policy: only ever rendered inside an already
 * policy-gated Customer/Lead show page, same precedent as
 * CallVoiceTranscript — but mutating methods still check $canManage
 * defensively, same as RecordNotes.
 */
class MeetingImport extends Component
{
    public Model $record;

    public bool $canManage = false;

    public bool $showPicker = false;

    public ?array $events = null;

    public bool $showScheduler = false;

    public string $scheduleAt = '';

    public ?string $createdMeetLink = null;

    public ?string $error = null;

    public function mount(Model $record, bool $canManage = false): void
    {
        $this->record = $record;
        $this->canManage = $canManage;
    }

    public function openScheduler(): void
    {
        abort_unless($this->canManage, 403);

        $this->error = null;
        $this->createdMeetLink = null;
        $this->scheduleAt = now()->addMinutes(30)->format('Y-m-d\TH:i');
        $this->showScheduler = true;
    }

    public function cancelScheduler(): void
    {
        $this->showScheduler = false;
        $this->error = null;
    }

    /** The email to invite — the client's own billing contact, or the lead's email. Never a picker; matches how billingEmail() already drives invoice emails. */
    public function attendeeEmail(): ?string
    {
        return $this->record instanceof Customer
            ? $this->record->billingEmail()
            : ($this->record->email ?? null);
    }

    public function createMeeting(GoogleCalendarClient $calendar): void
    {
        abort_unless($this->canManage, 403);

        $this->error = null;

        $connection = GoogleAccountConnection::forCompany();

        if (! $connection) {
            $this->error = 'No Google account connected yet — ask an admin to connect one in Settings.';

            return;
        }

        $start = Carbon::parse($this->scheduleAt);
        $title = 'NEDS <> '.($this->record->company_name ?? $this->record->company ?? $this->record->name ?? 'Meeting');
        $attendeeEmail = $this->attendeeEmail();

        $result = $calendar->createMeetingEvent($connection, $title, $attendeeEmail, $start);

        if ($result === null) {
            $this->error = "Couldn't create the meeting — please try again.";

            return;
        }

        $this->record->meetings()->create([
            'user_id' => auth()->id(),
            'google_event_id' => $result['event_id'],
            'title' => $title,
            'occurred_at' => $start,
            'attendees' => $attendeeEmail ? [$attendeeEmail] : [],
        ]);

        $this->createdMeetLink = $result['meet_link'];
        $this->showScheduler = false;
    }

    public function loadEvents(GoogleCalendarClient $calendar): void
    {
        abort_unless($this->canManage, 403);

        $this->error = null;
        $connection = GoogleAccountConnection::forCompany();

        if (! $connection) {
            $this->error = 'No Google account connected yet — ask an admin to connect one in Settings.';

            return;
        }

        $events = $calendar->listRecentMeetEvents($connection);

        if ($events === null) {
            $this->error = "Couldn't load the Calendar events — please try again.";
            $this->events = [];
        } else {
            $this->events = $events;
        }

        $this->showPicker = true;
    }

    public function cancelPicker(): void
    {
        $this->showPicker = false;
        $this->events = null;
        $this->error = null;
    }

    public function importEvent(string $eventId, GoogleMeetImportClient $importer): void
    {
        abort_unless($this->canManage, 403);

        $this->error = null;

        if (Meeting::where('google_event_id', $eventId)->exists()) {
            $this->error = 'This meeting has already been imported.';

            return;
        }

        $connection = GoogleAccountConnection::forCompany();

        if (! $connection) {
            $this->error = 'No Google account connected yet — ask an admin to connect one in Settings.';

            return;
        }

        $detail = $importer->fetchEventDetail($connection, $eventId);

        if ($detail === null) {
            $this->error = "Couldn't import that meeting — please try again.";

            return;
        }

        $meeting = $this->record->meetings()->create([
            'user_id' => auth()->id(),
            'google_event_id' => $eventId,
            ...$detail,
        ]);

        if (filled($meeting->raw_transcript) && GoogleMeet::summaryEnabled()) {
            $meeting->forceFill(['ai_summary_status' => MeetingSummaryStatus::Pending])->saveQuietly();
            SummarizeMeeting::dispatch($meeting->id);
        }

        $this->showPicker = false;
        $this->events = null;
    }

    public function syncRecording(int $meetingId, GoogleMeetImportClient $importer): void
    {
        abort_unless($this->canManage, 403);

        $this->error = null;
        $meeting = $this->record->meetings()->findOrFail($meetingId);

        $connection = GoogleAccountConnection::forCompany();

        if (! $connection) {
            $this->error = 'No Google account connected yet — ask an admin to connect one in Settings.';

            return;
        }

        $detail = $importer->fetchEventDetail($connection, $meeting->google_event_id);

        if ($detail === null) {
            $this->error = "Couldn't sync that meeting yet — try again in a few minutes.";

            return;
        }

        $meeting->update($detail);

        if (filled($meeting->raw_transcript) && GoogleMeet::summaryEnabled() && $meeting->ai_summary_status === null) {
            $meeting->forceFill(['ai_summary_status' => MeetingSummaryStatus::Pending])->saveQuietly();
            SummarizeMeeting::dispatch($meeting->id);
        }
    }

    public function render()
    {
        return view('livewire.meeting-import', [
            'meetings' => $this->record->meetings()->with('user:id,name')->get(),
            'connected' => (bool) GoogleAccountConnection::forCompany(),
            'featureEnabled' => GoogleMeet::enabled(),
        ]);
    }
}
