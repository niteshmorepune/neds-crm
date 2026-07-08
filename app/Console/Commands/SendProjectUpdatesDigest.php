<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Mail\ProjectUpdatesDigest;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Daily admin/manager leadership digest covering the project daily-update
 * workflow (see DraftProjectDailyUpdate): how yesterday's AI-drafted client
 * updates were handled, which drafts have sat unapproved too long, and which
 * active projects have gone quiet — so neither a client update nor a stalled
 * project silently falls through the cracks. Recomputes live state on every
 * run (no "already sent" suppression) so a still-unresolved item keeps
 * surfacing until it's actually dealt with.
 */
class SendProjectUpdatesDigest extends Command
{
    protected $signature = 'app:send-project-updates-digest
                            {--stale-days=2 : Days a drafted client update can sit unapproved before it is flagged}
                            {--quiet-days=5 : Days of no completed task or note before an active project is flagged as quiet}';

    protected $description = 'Daily admin/manager digest of client-update drafting, stale drafts, and quiet projects (run at 09:15 IST).';

    public function handle(): int
    {
        $tz = config('app.display_timezone');
        $today = Carbon::today($tz);

        if ($today->isSunday()) {
            $this->info('Sunday — skipping project updates digest.');

            return self::SUCCESS;
        }

        $staleDays = (int) $this->option('stale-days');
        $quietDays = (int) $this->option('quiet-days');

        $yesterday = $today->copy()->subDay()->toDateString();

        $yesterdaysDrafts = Note::where('notable_type', Project::class)
            ->where('ai_generated', true)
            ->whereDate('created_at', $yesterday)
            ->get();

        $staleDrafts = Note::where('notable_type', Project::class)
            ->where('ai_generated', true)
            ->where('visible_to_client', false)
            ->where('created_at', '<', now()->subDays($staleDays))
            ->with('notable.owner', 'notable.customer')
            ->orderBy('created_at')
            ->get();

        $quietCutoff = now()->subDays($quietDays);
        $quietProjects = Project::query()
            ->where('status', ProjectStatus::Active)
            ->where('created_at', '<', $quietCutoff)
            ->whereDoesntHave('tasks', fn ($q) => $q->where('status', TaskStatus::Done)->where('completed_at', '>=', $quietCutoff))
            ->whereDoesntHave('notes', fn ($q) => $q->where('created_at', '>=', $quietCutoff))
            ->with('owner', 'customer')
            ->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'lastActivityAt' => $this->lastActivityAt($project),
            ]);

        if ($yesterdaysDrafts->isEmpty() && $staleDrafts->isEmpty() && $quietProjects->isEmpty()) {
            $this->info('Nothing to report.');

            return self::SUCCESS;
        }

        $recipients = User::where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new ProjectUpdatesDigest(
                $recipient, $yesterdaysDrafts, $staleDrafts, $quietProjects, $staleDays, $quietDays,
            ));
        }

        $this->info("Sent project updates digest to {$recipients->count()} admin/manager(s).");

        return self::SUCCESS;
    }

    private function lastActivityAt(Project $project): ?Carbon
    {
        $lastTaskDone = $project->tasks()->where('status', TaskStatus::Done)->max('completed_at');
        $lastNote = $project->notes()->max('created_at');

        return collect([$lastTaskDone, $lastNote])
            ->filter()
            ->map(fn ($d) => Carbon::parse($d))
            ->sortDesc()
            ->first();
    }
}
