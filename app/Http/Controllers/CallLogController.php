<?php

namespace App\Http\Controllers;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\UserRole;
use App\Enums\VoiceTranscriptStatus;
use App\Http\Requests\CallLogStoreRequest;
use App\Jobs\TranscribeCallLogVoiceNote;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\MenuResolver;
use App\Support\Ai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CallLogController extends Controller
{
    public function __construct(private readonly MenuResolver $menu) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CallLog::class);

        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $calls = CallLog::query()
            ->with(['user', 'callable'])
            ->unless($isManager, fn ($q) => $q->where('user_id', $user->id))
            ->when($isManager && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->input('outcome')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('called_at', $request->date('date')))
            ->when($request->boolean('pending_followup'), fn ($q) => $q->whereNotNull('follow_up_at')->whereNull('follow_up_notified_at'))
            ->latest('called_at')
            ->paginate(20)
            ->withQueryString();

        return view('calls.index', [
            'calls' => $calls,
            'staff' => $isManager ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'outcomes' => CallOutcome::cases(),
            'filters' => $request->only(['user_id', 'outcome', 'date', 'pending_followup']),
            'isManager' => $isManager,
            'canLogLeads' => $this->menu->canAccess($user, 'lead-generation'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CallLog::class);

        $canLogLeads = $this->menu->canAccess($request->user(), 'lead-generation');

        return view('calls.create', [
            'directions' => CallDirection::cases(),
            'outcomes' => CallOutcome::cases(),
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'leads' => $canLogLeads ? Lead::orderBy('name')->get(['id', 'name']) : collect(),
            'aiEnabled' => Ai::voiceTranscriptionEnabled(),
            'selectedCustomer' => $request->integer('customer_id') ?: null,
            'selectedLead' => $request->integer('lead_id') ?: null,
        ]);
    }

    public function store(CallLogStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', CallLog::class);

        $data = $request->validated();

        [$type, $id] = match (true) {
            ! empty($data['customer_id']) => [Customer::class, $data['customer_id']],
            ! empty($data['lead_id']) => [Lead::class, $data['lead_id']],
            default => [null, null],
        };

        $tz = config('app.display_timezone');

        $call = CallLog::create([
            'user_id' => $request->user()->id,
            'callable_type' => $type,
            'callable_id' => $id,
            'direction' => $data['direction'],
            'outcome' => $data['outcome'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'notes' => $data['notes'] ?? null,
            'called_at' => Carbon::parse($data['called_at'], $tz)->utc(),
            'next_action' => $data['next_action'] ?? null,
            'follow_up_at' => filled($data['follow_up_at'] ?? null)
                ? Carbon::parse($data['follow_up_at'], $tz)->utc()
                : null,
        ]);

        if ($request->hasFile('voice_note') && Ai::voiceTranscriptionEnabled()) {
            $this->attachVoiceNote($call, $request);
        }

        $this->clearSupersededFollowUps($call, $type, $id);

        // Return to the linked record's page when logged from there.
        if ($call->callable_type === Customer::class) {
            return redirect()->route('clients.show', $call->callable_id)->with('status', 'Call logged.');
        }
        if ($call->callable_type === Lead::class) {
            return redirect()->route('leads.show', $call->callable_id)->with('status', 'Call logged.');
        }

        return redirect()->route('calls.index')->with('status', 'Call logged.');
    }

    /**
     * A newly-logged call that actually reached the client/lead supersedes
     * any earlier pending reminder set on a previous call to the same
     * record — otherwise a rep who calls back early (or right on schedule)
     * still gets nagged later by a follow-up reminder for contact that's
     * already happened. Only clears reminders that haven't fired yet
     * (`follow_up_notified_at` still null); a reminder that already sent
     * is left alone as a historical record. Scoped to outcomes that mean
     * the client was actually reached — a NoAnswer/Busy attempt doesn't
     * resolve anything, the old reminder should still stand.
     */
    private function clearSupersededFollowUps(CallLog $call, ?string $type, ?int $id): void
    {
        if (! $type || ! $id) {
            return;
        }

        if (! in_array($call->outcome, [CallOutcome::Connected, CallOutcome::FollowUpNeeded], true)) {
            return;
        }

        CallLog::where('callable_type', $type)
            ->where('callable_id', $id)
            ->where('id', '!=', $call->id)
            ->whereNotNull('follow_up_at')
            ->whereNull('follow_up_notified_at')
            ->update(['follow_up_at' => null]);
    }

    private function attachVoiceNote(CallLog $call, Request $request): void
    {
        $file = $request->file('voice_note');

        $attachment = $call->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $file->store('call-voice-notes', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        $call->forceFill(['voice_transcript_status' => VoiceTranscriptStatus::Pending])->saveQuietly();

        TranscribeCallLogVoiceNote::dispatch($call->id, $attachment->id);
    }
}
