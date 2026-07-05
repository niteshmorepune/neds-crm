<?php

namespace App\Jobs;

use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Enums\UserRole;
use App\Models\ContentPiece;
use App\Models\Festival;
use App\Models\Project;
use App\Models\User;
use App\Notifications\FestivalGreetingDrafted;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Drafts an AI festival-greeting caption for one project ahead of one
 * festival and lands it in the Content Collaboration queue as a draft
 * (never sent/published automatically). Referenced by id, not a serialized
 * model, so a re-run always sees fresh data. AI failure is swallowed — this
 * must never break the scheduling command or the festival calendar.
 */
class DraftFestivalGreetingContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $festivalId, public int $projectId) {}

    public function handle(AiAssistant $ai): void
    {
        if (! Ai::enabled()) {
            return;
        }

        // Idempotency: one greeting draft per project per festival.
        if (ContentPiece::where('project_id', $this->projectId)->where('festival_id', $this->festivalId)->exists()) {
            return;
        }

        $festival = Festival::find($this->festivalId);
        $project = Project::with('customer', 'service', 'assignees')->find($this->projectId);

        if ($festival === null || $project === null) {
            return;
        }

        $draft = $ai->draftFestivalGreeting($festival, $project);

        if ($draft === null) {
            return;
        }

        $platform = $project->service?->slug === 'gmb'
            ? ContentPlatform::GoogleBusiness
            : ContentPlatform::Instagram;

        $lead = $project->assignees->firstWhere('pivot.role', 'lead') ?? $project->assignees->first();
        $createdBy = $lead?->id ?? $project->owner_id
            ?? User::where('role', UserRole::Admin->value)->value('id');

        $piece = ContentPiece::create([
            'project_id' => $project->id,
            'workflow_type' => ContentWorkflowType::NedsLed,
            'platform' => $platform,
            'status' => ContentStatus::initialFor(ContentWorkflowType::NedsLed),
            'title' => "{$festival->name} greeting — {$project->customer->company_name}",
            'copy_text' => $draft,
            'publish_date' => $festival->date,
            'festival_id' => $festival->id,
            'created_by' => $createdBy,
        ]);

        $recipient = $lead ?? $project->owner ?? User::where('role', UserRole::Admin->value)->first();

        if ($recipient) {
            $recipient->notify(new FestivalGreetingDrafted($piece));
        }
    }
}
