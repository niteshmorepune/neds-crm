<?php

namespace App\Policies;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->user_id === $user->id || $this->manages($user);
    }

    public function viewApprovalQueue(User $user): bool
    {
        return $this->manages($user);
    }

    /**
     * Approve or reject. Any admin/manager may act, except on their own
     * request — self-approval is never allowed.
     */
    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->manages($user) && $leaveRequest->user_id !== $user->id;
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->user_id === $user->id
            && $leaveRequest->status === LeaveRequestStatus::Pending;
    }

    private function manages(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
