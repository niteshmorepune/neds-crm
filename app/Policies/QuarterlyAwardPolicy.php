<?php

namespace App\Policies;

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\QuarterlyAward;
use App\Models\User;

class QuarterlyAwardPolicy
{
    /**
     * Everyone may view the index — Admin/Manager see the full queue,
     * everyone else only their own approved awards (see view()).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admin/Manager can view any award. A candidate can see their own
     * award only once it's Approved — a Pending suggestion is internal
     * deliberation, not something to surface to the person it's about
     * before a decision is made.
     */
    public function view(User $user, QuarterlyAward $award): bool
    {
        return $this->manages($user)
            || ($award->user_id === $user->id && $award->status === AwardStatus::Approved);
    }

    public function review(User $user, QuarterlyAward $award): bool
    {
        return $this->manages($user);
    }

    public function regenerate(User $user): bool
    {
        return $this->manages($user);
    }

    public function downloadCertificate(User $user, QuarterlyAward $award): bool
    {
        return $award->status === AwardStatus::Approved && $this->view($user, $award);
    }

    private function manages(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
