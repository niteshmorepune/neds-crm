<?php

namespace App\Services\NextAction;

use App\Actions\CreateProjectFromDeal;
use App\Contracts\NextActionSource;
use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\NextActionSnooze;
use App\Models\Project;
use App\Models\User;
use App\Support\NextAction;

/**
 * Phase 7 of the Next Action Engine (Sales journey installment 1, see
 * QuotationFollowUpSource for the shared context). The other real,
 * previously-unsignaled gap: winning a Deal never auto-creates a Project
 * (see App\Actions\CreateProjectFromDeal — a separate, manual step) — so a
 * deal can sit Won indefinitely with delivery never actually started, and
 * nothing in this app currently flags that. Since CreateProjectFromDeal
 * needs no input beyond the deal itself and already guards its own
 * preconditions, this resolves inline (one click) by calling that exact
 * same Action class, not a duplicated creation path.
 */
class DealWonNoProjectSource implements NextActionSource
{
    public function key(): string
    {
        return 'deal_won_no_project';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Sales)) {
            return null;
        }

        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Deal::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $deal = Deal::where('owner_id', $user->id)
            ->where('stage', DealStage::Won)
            ->whereDoesntHave('project')
            ->whereNotIn('id', $snoozedIds)
            ->oldest('won_at')
            ->first();

        if ($deal === null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Deal::class,
            subjectId: $deal->id,
            title: "Start the project for {$deal->title}",
            body: 'Won '.($deal->won_at?->diffForHumans() ?? 'recently').' — no project created yet.',
            actionUrl: null,
            actionLabel: 'Create project now',
        );
    }

    public function complete(User $user, int $subjectId): void
    {
        abort_unless($user->can('create', Project::class), 403);

        $deal = Deal::findOrFail($subjectId);
        abort_unless($deal->owner_id === $user->id, 403);

        app(CreateProjectFromDeal::class)->handle($deal);
    }
}
