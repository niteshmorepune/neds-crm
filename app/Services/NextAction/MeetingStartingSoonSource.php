<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\MeetingParticipantStatus;
use App\Models\Meeting;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Support\NextAction;

/**
 * Alerts whoever organizes or is invited to a Meeting (see
 * MeetingImport::createMeeting() / MeetingParticipant) that it's about to
 * start, so they don't miss joining — applies to every role, no role gate.
 * A participant who has explicitly Declined is excluded (mirrors
 * MyMeetingInvitations' own eligibility); the organizer is always eligible,
 * since they can't decline their own meeting. Deliberately time-window
 * based rather than resolvable — there's no "complete" action, it just
 * stops applying once the meeting is far enough in the past.
 */
class MeetingStartingSoonSource implements NextActionSource
{
    private const LOOKAHEAD_MINUTES = 10;

    private const GRACE_MINUTES = 5;

    public function key(): string
    {
        return 'meeting_starting_soon';
    }

    public function next(User $user): ?NextAction
    {
        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Meeting::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $meeting = Meeting::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('participants', fn ($q) => $q->where('user_id', $user->id)
                    ->where('status', '!=', MeetingParticipantStatus::Declined->value));
        })
            ->whereNotNull('meet_link')
            ->whereBetween('occurred_at', [now()->subMinutes(self::GRACE_MINUTES), now()->addMinutes(self::LOOKAHEAD_MINUTES)])
            ->whereNotIn('id', $snoozedIds)
            ->orderBy('occurred_at')
            ->first();

        if ($meeting === null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Meeting::class,
            subjectId: $meeting->id,
            title: "Join: {$meeting->title}",
            body: $meeting->occurred_at->isFuture()
                ? 'Starts '.$meeting->occurred_at->diffForHumans()
                : 'Started '.$meeting->occurred_at->diffForHumans(),
            actionUrl: $meeting->meet_link,
            actionLabel: 'Join now',
            external: true,
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the Meet call),
     * so the banner never renders a button for it and this should never be
     * reachable — throwing surfaces a wiring bug loudly instead of silently
     * no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
