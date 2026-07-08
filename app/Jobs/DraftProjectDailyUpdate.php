<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\ProjectDailyUpdateDrafted;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Drafts an AI "here's today's progress" note for one project and lands it
 * as a pending Note (ai_generated=true, visible_to_client=false) on the
 * project's timeline — never sent automatically. The project owner (or an
 * admin/manager) reviews, edits if needed, and approves via
 * ProjectDailyUpdateReview, which is what actually shares it with the
 * client (portal + email). Referenced by project id, not a serialized
 * model, so a re-run always sees fresh data.
 */
class DraftProjectDailyUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTIVITY_EVENT = 'project_daily_update_drafted';

    /** @param  string  $date  Y-m-d of the day being reported on. */
    public function __construct(public int $projectId, public string $date) {}

    public function handle(AiAssistant $ai): void
    {
        if (! Ai::enabled()) {
            return;
        }

        // Idempotency: one draft per project per day (defense in depth — the
        // dispatching command already checks this before dispatching).
        if ($this->alreadyDrafted()) {
            return;
        }

        $project = Project::with('customer', 'owner')->find($this->projectId);

        if ($project === null || $project->customer === null) {
            return;
        }

        $completedTasks = Task::where('project_id', $project->id)
            ->where('status', TaskStatus::Done)
            ->whereDate('completed_at', $this->date)
            ->orderBy('completed_at')
            ->get();

        // Nothing happened today — skip silently, no hollow note.
        if ($completedTasks->isEmpty()) {
            return;
        }

        $draft = $ai->draftProjectDailyUpdate($project, $completedTasks);

        if ($draft === null) {
            return;
        }

        $project->notes()->create([
            'user_id' => null,
            'body' => $draft,
            'visible_to_client' => false,
            'ai_generated' => true,
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'event' => self::ACTIVITY_EVENT,
            'changes' => ['date' => $this->date],
        ]);

        $project->owner?->notify(new ProjectDailyUpdateDrafted($project, $this->date));
    }

    private function alreadyDrafted(): bool
    {
        return Activity::where('subject_type', Project::class)
            ->where('subject_id', $this->projectId)
            ->where('event', self::ACTIVITY_EVENT)
            ->whereJsonContains('changes->date', $this->date)
            ->exists();
    }
}
