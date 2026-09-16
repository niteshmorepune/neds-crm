<?php

namespace App\Console\Commands;

use App\Enums\LeadGoal;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadStagnationEscalatedNotification;
use App\Notifications\LeadWantsExpertAdviceNotification;
use App\Notifications\NotSureLeadEscalatedNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * A lead whose goal is "Not Sure – Need Expert Advice" explicitly asked for
 * a human -- LeadObserver's LeadWantsExpertAdviceNotification already pings
 * the owner/telecaller the moment that happens, but nothing follows up if
 * it goes unanswered. This is the missing follow-up layer, tiered like
 * SendStagnationAlerts/EscalateUntouchedLeads but on its own, much tighter
 * clock (hours, not days) -- a real hand-raise shouldn't wait as long as
 * routine drift before anyone notices. Runs hourly (routes/console.php) so
 * the tighter thresholds actually land close to real time; a daily cron
 * would be too coarse for a same-day expectation.
 *
 * "Untouched" reuses Lead::hasStaffEngagementSince() (a call, a staff-
 * written note, or a staff WhatsApp reply) -- the same definition every
 * other funnel/nudge job in this codebase already uses, measured from
 * notsure_at (stamped by LeadObserver whenever goal transitions, or is set
 * at creation, to NotSure -- see LeadObserver::stampNotSureBaseline()).
 *
 * Deliberately does NOT skip Sunday, unlike SendStagnationAlerts -- that
 * command is a weekly-cadence safety net for routine neglect; this one is
 * about a same-day response to someone who explicitly asked for help today,
 * which doesn't stop mattering because it's a Sunday.
 */
class EscalateNotSureLeads extends Command
{
    protected $signature = 'app:escalate-notsure-leads
                            {--owner-hours=6 : Hours of zero staff engagement since goal became Not Sure before nagging the owner/telecaller}
                            {--manager-hours=18 : Additional hours past owner-hours, still with zero engagement, before escalating to Admin/Manager}';

    protected $description = 'Follow-up escalation for a lead that asked for expert advice (goal = Not Sure) and got no staff engagement -- nags the owner/telecaller, then escalates to Admin/Manager if it stays unaddressed (run hourly).';

    /**
     * Once escalated to managers, don't re-fire on every hourly tick --
     * mirrors SendStagnationAlerts' manager tier "re-fire daily while
     * unaddressed" behaviour without literally requiring a 24h gap (which
     * would drift later every day against a jittery cron); a cooldown a
     * little under a day keeps it to roughly once per day.
     */
    private const MANAGER_REFIRE_COOLDOWN_HOURS = 20;

    public function handle(): int
    {
        $ownerHours = (int) $this->option('owner-hours');
        $managerHours = (int) $this->option('manager-hours');

        $ownerCutoff = now()->subHours($ownerHours);
        $managerCutoff = $ownerCutoff->copy()->subHours($managerHours);

        $this->nagOwners($ownerCutoff);
        $this->escalateToManagers($managerCutoff, $ownerHours + $managerHours);

        return self::SUCCESS;
    }

    /**
     * @return Builder<Lead>
     */
    private function baseQuery(): Builder
    {
        return Lead::query()
            ->where('goal', LeadGoal::NotSure->value)
            ->whereNotIn('status', [LeadStatus::Converted->value, LeadStatus::Lost->value])
            ->whereNotNull('owner_id')
            ->whereNotNull('notsure_at');
    }

    /**
     * @return Collection<int, Lead>
     */
    private function untouchedSince(Builder $query, Carbon $cutoff): Collection
    {
        return $query
            ->where('notsure_at', '<=', $cutoff)
            ->with(['owner', 'telecaller'])
            ->get()
            ->reject(fn (Lead $lead) => $lead->hasStaffEngagementSince($lead->notsure_at))
            ->values();
    }

    private function nagOwners(Carbon $ownerCutoff): void
    {
        $leads = $this->untouchedSince(
            $this->baseQuery()->whereNull('notsure_owner_notified_at'),
            $ownerCutoff,
        );

        foreach ($leads as $lead) {
            $notification = new LeadWantsExpertAdviceNotification($lead, isReminder: true);

            $recipients = collect([$lead->owner_id, $lead->telecaller_id])->filter()->unique();
            User::whereIn('id', $recipients)->get()->each->notify($notification);

            $lead->forceFill(['notsure_owner_notified_at' => now()])->saveQuietly();
        }

        if ($leads->isNotEmpty()) {
            $this->info("Nagged owner/telecaller on {$leads->count()} Not-Sure lead(s).");
        }
    }

    private function escalateToManagers(Carbon $managerCutoff, int $totalHours): void
    {
        $leads = $this->untouchedSince(
            $this->baseQuery()->whereNotNull('notsure_owner_notified_at'),
            $managerCutoff,
        )
            ->reject(fn (Lead $lead) => $this->recentlyEscalated($lead))
            ->reject(fn (Lead $lead) => $this->alreadyEscalatedByStagnationAlerts($lead));

        if ($leads->isEmpty()) {
            return;
        }

        $managers = User::where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        foreach ($leads as $lead) {
            $notification = new NotSureLeadEscalatedNotification($lead, $totalHours);
            $managers->each(fn (User $manager) => $manager->notify($notification));

            $lead->forceFill(['notsure_manager_escalated_at' => now()])->saveQuietly();
        }

        $this->info("Escalated {$leads->count()} Not-Sure lead(s) to managers.");
    }

    private function recentlyEscalated(Lead $lead): bool
    {
        return $lead->notsure_manager_escalated_at !== null
            && $lead->notsure_manager_escalated_at->gt(now()->subHours(self::MANAGER_REFIRE_COOLDOWN_HOURS));
    }

    /**
     * Both this command's manager tier and SendStagnationAlerts' own manager
     * tier re-fire daily for as long as a lead stays untouched -- a Not-Sure
     * lead that also goes unaddressed long enough (7-10 days) eventually
     * falls into SendStagnationAlerts' own net too (goal isn't excluded from
     * the activity log, so the transition itself briefly hides it from that
     * command's query, but once that activity ages past its own cutoffs the
     * lead reappears there as "generically stagnant"). Rather than touch
     * SendStagnationAlerts to make it aware of this command, check the one
     * real fact that matters: did a LeadStagnationEscalatedNotification
     * already reach the managers about this exact lead today? If so, skip
     * -- the team's already been told, and a second, differently-worded
     * escalation about the same silence on the same day would just be
     * noise. Scoped to "today" (Asia/Kolkata) rather than any escalation
     * ever, so this command resumes escalating on its own the next day if
     * SendStagnationAlerts doesn't fire again.
     */
    private function alreadyEscalatedByStagnationAlerts(Lead $lead): bool
    {
        $todayStart = now(config('app.display_timezone'))->startOfDay();

        return DatabaseNotification::query()
            ->where('type', LeadStagnationEscalatedNotification::class)
            ->whereJsonContains('data->lead_id', $lead->id)
            ->where('created_at', '>=', $todayStart)
            ->exists();
    }
}
