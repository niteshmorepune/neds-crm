<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleMeetImportClient;
use App\Support\GoogleMeet;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * "Import Meet Notes" — embedded on Customer/Lead pages (mirrors CallLog's
 * attach scope exactly). Lists the viewing user's own recent Google
 * Calendar events with a Meet link, imports the raw transcript/recording
 * Google Meet already generated for the one they pick. No AI step yet
 * (Phase 1) — Phase 2 adds a Claude-summarized version on top of the raw
 * transcript stored here. No dedicated Policy: only ever rendered inside an
 * already policy-gated Customer/Lead show page, same precedent as
 * CallVoiceTranscript — but mutating methods still check $canManage
 * defensively, same as RecordNotes.
 */
class MeetingImport extends Component
{
    public Model $record;

    public bool $canManage = false;

    public bool $showPicker = false;

    public ?array $events = null;

    public ?string $error = null;

    public function mount(Model $record, bool $canManage = false): void
    {
        $this->record = $record;
        $this->canManage = $canManage;
    }

    public function loadEvents(GoogleCalendarClient $calendar): void
    {
        abort_unless($this->canManage, 403);

        $this->error = null;
        $connection = auth()->user()->googleAccountConnection;

        if (! $connection) {
            $this->error = 'Connect your Google account in Settings first.';

            return;
        }

        $events = $calendar->listRecentMeetEvents($connection);

        if ($events === null) {
            $this->error = "Couldn't load your Calendar events — please try again.";
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

        $connection = auth()->user()->googleAccountConnection;

        if (! $connection) {
            $this->error = 'Connect your Google account in Settings first.';

            return;
        }

        $detail = $importer->fetchEventDetail($connection, $eventId);

        if ($detail === null) {
            $this->error = "Couldn't import that meeting — please try again.";

            return;
        }

        $this->record->meetings()->create([
            'user_id' => auth()->id(),
            'google_event_id' => $eventId,
            ...$detail,
        ]);

        $this->showPicker = false;
        $this->events = null;
    }

    public function render()
    {
        return view('livewire.meeting-import', [
            'meetings' => $this->record->meetings()->with('user:id,name')->get(),
            'connected' => (bool) auth()->user()->googleAccountConnection,
            'featureEnabled' => GoogleMeet::enabled(),
        ]);
    }
}
