<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Support\NextAction;
use Illuminate\Support\Carbon;

/**
 * wadesk.in's after-hours AI assistant runs off a per-number weekly
 * business-hours schedule (e.g. Mon-Sat 10:00-19:00) with no lunch
 * carve-out, plus a manual per-number FORCE_ON/FORCE_OFF/AUTO override —
 * during the 1-2pm lunch window the Marketing line reads as "open," so the
 * AI stays off by default even though nobody's actually watching WhatsApp.
 * Confirmed by reading wadesk.in's own business-hours.ts and numbers
 * page.tsx directly, not assumed. That override page is ADMIN-only on
 * wadesk.in, so this source is gated to CRM Admin/Manager — the people who
 * realistically hold that access — not Sales/Telecaller.
 *
 * Deliberately reminder-only, not a toggle (confirmed with the owner via
 * AskUserQuestion): this source has no way to read wadesk.in's live AiMode
 * back, so it can't tell whether the toggle was actually flipped, only
 * that it's a moment worth reminding about — a plain link out, snoozable
 * like everything else, never a "complete" button.
 */
class LunchHourWadeskAiSource implements NextActionSource
{
    private const TURN_ON_WINDOW = ['12:55', '13:10'];

    private const TURN_OFF_WINDOW = ['13:55', '14:10'];

    private const SUBJECT_TYPE = 'wadesk_lunch_ai_toggle';

    public function key(): string
    {
        return 'lunch_hour_wadesk_ai';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return null;
        }

        $now = Carbon::now(config('app.display_timezone'));

        // Office is Mon-Sat (same convention as LeaveRequest::businessDays()) —
        // wadesk's own schedule already has the AI on all day Sunday anyway.
        if ($now->isSunday()) {
            return null;
        }

        $timeOfDay = $now->format('H:i');
        $isTurnOnWindow = $timeOfDay >= self::TURN_ON_WINDOW[0] && $timeOfDay < self::TURN_ON_WINDOW[1];
        $isTurnOffWindow = $timeOfDay >= self::TURN_OFF_WINDOW[0] && $timeOfDay < self::TURN_OFF_WINDOW[1];

        if (! $isTurnOnWindow && ! $isTurnOffWindow) {
            return null;
        }

        // One synthetic subject id per calendar day per window, so today's
        // turn-on and turn-off reminders (and each new day's) snooze
        // independently instead of colliding.
        $subjectId = ((int) $now->format('Ymd')) * 10 + ($isTurnOnWindow ? 1 : 2);

        $isSnoozed = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', self::SUBJECT_TYPE)
            ->where('subject_id', $subjectId)
            ->where('snoozed_until', '>', now())
            ->exists();

        if ($isSnoozed) {
            return null;
        }

        $numbersUrl = rtrim(config('services.wadesk.base_url'), '/').'/numbers';

        return $isTurnOnWindow
            ? new NextAction(
                sourceKey: $this->key(),
                subjectType: self::SUBJECT_TYPE,
                subjectId: $subjectId,
                title: 'Turn on lunch-hour AI replies',
                body: "It's lunch (1-2pm) — turn on the WhatsApp AI assistant on the Marketing line so no message goes unanswered.",
                actionUrl: $numbersUrl,
                actionLabel: 'Open WhatsApp Numbers',
                external: true,
            )
            : new NextAction(
                sourceKey: $this->key(),
                subjectType: self::SUBJECT_TYPE,
                subjectId: $subjectId,
                title: 'Turn off lunch-hour AI replies',
                body: 'Lunch is ending — switch the Marketing line back to Auto so real replies resume.',
                actionUrl: $numbersUrl,
                actionLabel: 'Open WhatsApp Numbers',
                external: true,
            );
    }

    /**
     * This source's prompt always sets actionUrl (a link to wadesk.in), so
     * the banner never renders a button for it and this should never be
     * reachable — throwing surfaces a wiring bug loudly instead of
     * silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
