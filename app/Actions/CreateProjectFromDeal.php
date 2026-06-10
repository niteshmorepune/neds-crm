<?php

namespace App\Actions;

use App\Enums\DealStage;
use App\Enums\ProjectStatus;
use App\Models\Deal;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateProjectFromDeal
{
    /**
     * Create a project from a won deal. Carries over customer, service and
     * owner; attaches the given assignees. One project per deal.
     *
     * @param  array<int, int>  $assigneeIds
     */
    public function handle(Deal $deal, array $assigneeIds = []): Project
    {
        if ($deal->stage !== DealStage::Won) {
            throw new RuntimeException('A project can only be created from a won deal.');
        }

        if (Project::where('deal_id', $deal->id)->exists()) {
            throw new RuntimeException('This deal already has a project.');
        }

        return DB::transaction(function () use ($deal, $assigneeIds) {
            $project = Project::create([
                'name' => $deal->title,
                'customer_id' => $deal->customer_id,
                'deal_id' => $deal->id,
                'service_id' => $deal->service_id,
                'owner_id' => $deal->owner_id,
                'status' => ProjectStatus::Active->value,
                'start_date' => now()->toDateString(),
            ]);

            $ids = array_unique(array_filter($assigneeIds));
            if ($deal->owner_id) {
                $ids[] = $deal->owner_id;
            }
            $project->assignees()->sync(array_unique($ids));

            return $project;
        });
    }
}
