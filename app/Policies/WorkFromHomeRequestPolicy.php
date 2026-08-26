<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\WorkFromHomeRequestStatus;
use App\Models\User;
use App\Models\WorkFromHomeRequest;

class WorkFromHomeRequestPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkFromHomeRequest $workFromHomeRequest): bool
    {
        return $workFromHomeRequest->user_id === $user->id || $this->manages($user);
    }

    public function viewApprovalQueue(User $user): bool
    {
        return $this->manages($user);
    }

    /**
     * Approve or reject. Any admin/manager may act, except on their own
     * request — self-approval is never allowed. Same rule as
     * LeaveRequestPolicy::review().
     */
    public function review(User $user, WorkFromHomeRequest $workFromHomeRequest): bool
    {
        return $this->manages($user) && $workFromHomeRequest->user_id !== $user->id;
    }

    public function delete(User $user, WorkFromHomeRequest $workFromHomeRequest): bool
    {
        return $workFromHomeRequest->user_id === $user->id
            && $workFromHomeRequest->status === WorkFromHomeRequestStatus::Pending;
    }

    private function manages(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
