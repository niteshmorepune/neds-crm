<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ContentPiece;
use App\Models\Project;
use App\Models\User;

class ContentPiecePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return app(ProjectPolicy::class)->view($user, $project);
    }

    public function view(User $user, ContentPiece $piece): bool
    {
        return app(ProjectPolicy::class)->view($user, $piece->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager)
            || $project->owner_id === $user->id;
    }

    public function update(User $user, ContentPiece $piece): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager)
            || $piece->project->owner_id === $user->id;
    }

    public function delete(User $user, ContentPiece $piece): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager)
            || $piece->project->owner_id === $user->id;
    }

    public function generateUploadLink(User $user, ContentPiece $piece): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
