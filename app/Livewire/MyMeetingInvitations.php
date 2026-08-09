<?php

namespace App\Livewire;

use App\Enums\MeetingParticipantStatus;
use App\Models\MeetingParticipant;
use Livewire\Component;

/**
 * Dashboard widget: meetings the viewer has been invited to as an internal
 * team member (see MeetingImport::createMeeting()), covering the "sees the
 * meeting on their CRM dashboard" half of that feature — a full Manager
 * Calendar is a separate, larger item, not built here. Shows only meetings
 * that haven't happened yet, soonest first; a past meeting simply drops off
 * rather than needing a "Completed" state tracked anywhere.
 */
class MyMeetingInvitations extends Component
{
    /** Only ever Accepted or Declined from here — Pending is the default state, never something to set back to. */
    public function respond(int $participantId, string $status): void
    {
        abort_unless(in_array($status, [MeetingParticipantStatus::Accepted->value, MeetingParticipantStatus::Declined->value], true), 422);

        $participant = MeetingParticipant::findOrFail($participantId);
        abort_unless($participant->user_id === auth()->id(), 403);

        $participant->update(['status' => $status]);
    }

    public function render()
    {
        $participants = MeetingParticipant::where('user_id', auth()->id())
            ->whereHas('meeting', fn ($q) => $q->where('occurred_at', '>=', now()))
            ->with(['meeting.meetable', 'meeting.user'])
            ->get()
            ->sortBy(fn (MeetingParticipant $p) => $p->meeting->occurred_at)
            ->values();

        return view('livewire.my-meeting-invitations', [
            'participants' => $participants,
            'statuses' => MeetingParticipantStatus::cases(),
        ]);
    }
}
