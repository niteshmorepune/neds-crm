<?php

namespace App\Http\Controllers\Portal;

use App\Enums\DeliverableStatus;
use App\Enums\MeetingPlatform;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\GoogleAccountConnection;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Notifications\MeetingRequested;
use App\Services\GoogleCalendarClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends PortalController
{
    /**
     * Allowed portal attachment types — same narrower list as the portal
     * ticket upload, since this is also a public-facing form.
     */
    private const ATTACHMENT_RULES = ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt,csv,zip'];

    public function index(): View
    {
        return view('portal.projects.index', [
            'projects' => $this->customer()->projects()->with(['service', 'owner', 'assignees'])->latest()->paginate(15),
        ]);
    }

    public function show(int $project): View
    {
        $project = $this->customer()->projects()->findOrFail($project);
        $project->load([
            'notes' => fn ($q) => $q->where('visible_to_client', true)->with('author'),
            'service',
            'owner',
            'assignees',
            'deliverables' => fn ($q) => $q->with('attachments')->latest(),
        ]);

        return view('portal.projects.show', [
            'project' => $project,
            'planFrequency' => $project->planFrequency(),
            'timeline' => $this->buildTimeline($project),
        ]);
    }

    /**
     * Staff-driven progress history for the client-facing timeline: a
     * deliverable being marked Received, and project status changes.
     * Deliberately excludes client-initiated events (e.g. the client's own
     * Pending→Submitted upload) — the client already knows they just did
     * that — and excludes notes, which already have their own "Updates from
     * Our Team" section on this page.
     *
     * Reads directly from the `activities` table both models already write
     * to via the LogsActivity trait — no new tracking, just surfacing data
     * that already exists. Fetch-then-filter in PHP rather than a JSON-column
     * query since the per-project dataset is small and this avoids
     * MySQL-vs-SQLite JSON syntax differences between prod and tests.
     */
    private function buildTimeline(Project $project): Collection
    {
        $deliverableEvents = Activity::where('subject_type', ProjectDeliverable::class)
            ->whereIn('subject_id', $project->deliverables->pluck('id'))
            ->where('event', 'updated')
            ->get()
            ->filter(fn (Activity $a) => ($a->changes['status'] ?? null) === DeliverableStatus::Received->value)
            ->map(fn (Activity $a) => [
                'at' => $a->created_at,
                'label' => ($project->deliverables->firstWhere('id', $a->subject_id)?->title ?? 'A deliverable').' — marked Received',
            ]);

        $projectEvents = Activity::where('subject_type', Project::class)
            ->where('subject_id', $project->id)
            ->where('event', 'updated')
            ->get()
            ->filter(fn (Activity $a) => array_key_exists('status', $a->changes ?? []))
            ->map(fn (Activity $a) => [
                'at' => $a->created_at,
                'label' => 'Project status changed to '.ProjectStatus::from($a->changes['status'])->label(),
            ]);

        return $deliverableEvents->concat($projectEvents)->sortByDesc('at')->values();
    }

    public function uploadDeliverable(Request $request, int $project, int $deliverable): RedirectResponse
    {
        $project = $this->customer()->projects()->findOrFail($project);
        $deliverable = $project->deliverables()->findOrFail($deliverable);

        $data = $request->validate(['attachment' => self::ATTACHMENT_RULES]);

        $file = $data['attachment'];
        $deliverable->attachments()->create([
            'contact_id' => auth('portal')->id(),
            'disk' => 'local',
            'path' => $file->store('attachments', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        if ($deliverable->status === DeliverableStatus::Pending) {
            $deliverable->update(['status' => DeliverableStatus::Submitted]);
        }

        return back()->with('status', 'File uploaded — thank you!');
    }

    /**
     * Client-initiated meeting request. Tries to create a real Google
     * Calendar event via the same company-wide connection
     * MeetingImport::createMeeting() already uses (staff-side) — same
     * connection-existence check, deliberately not also gated on
     * GoogleMeet::enabled() here either, for the same reason. If no
     * connection exists, still records the request (with a synthetic
     * google_event_id, same "manual" pattern as
     * MeetingImport::saveManualMeeting()) so staff can schedule it by hand.
     * Either way the project's scheduling contact is notified in-app.
     */
    public function requestMeeting(Request $request, int $project, GoogleCalendarClient $calendar): RedirectResponse
    {
        $project = $this->customer()->projects()->findOrFail($project);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'client_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $schedulingContact = $project->schedulingContact();

        if (! $schedulingContact) {
            return back()->withErrors(['scheduled_at' => 'This project has no team member assigned yet — please raise a ticket instead.']);
        }

        $start = Carbon::createFromFormat('Y-m-d\TH:i', $data['scheduled_at'], config('app.display_timezone', 'Asia/Kolkata'))->utc();
        $customer = $this->customer();
        $title = 'NEDS <> '.$customer->company_name;
        $clientEmail = $customer->billingEmail();

        $connection = GoogleAccountConnection::forCompany();
        $result = $connection
            ? $calendar->createMeetingEvent($connection, $title, array_values(array_filter([$clientEmail, $schedulingContact->email])), $start)
            : null;

        $meeting = $customer->meetings()->create([
            'user_id' => $schedulingContact->id,
            'google_event_id' => $result['event_id'] ?? 'requested-'.Str::uuid(),
            // Other (not GoogleMeet) when no real event was created, so the
            // "Sync recording & transcript" action never offers itself
            // against a synthetic event ID with nothing real behind it.
            'platform' => $result ? MeetingPlatform::GoogleMeet->value : MeetingPlatform::Other->value,
            'meet_link' => $result['meet_link'] ?? null,
            'title' => $title,
            'occurred_at' => $start,
            'attendees' => $result ? array_values(array_filter([$clientEmail, $schedulingContact->email])) : null,
            'requested_by_client' => true,
            'client_note' => $data['client_note'] ?? null,
        ]);

        $schedulingContact->notify(new MeetingRequested($meeting));

        $status = $result
            ? 'Meeting requested — check your email for the calendar invite.'
            : 'Meeting requested — our team will confirm shortly.';

        return back()->with('status', $status);
    }
}
