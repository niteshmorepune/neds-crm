<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\User;

class ProjectDeliverablePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return app(ProjectPolicy::class)->view($user, $project);
    }

    public function view(User $user, ProjectDeliverable $deliverable): bool
    {
        return app(ProjectPolicy::class)->view($user, $deliverable->project);
    }

    public function update(User $user, ProjectDeliverable $deliverable): bool
    {
        return app(ProjectPolicy::class)->update($user, $deliverable->project);
    }
}
