<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Component;

/**
 * Renders the ranked Employee Performance table (Score/Rank/Focus area, on
 * top of ReportMetrics::rankedEmployeePerformance()) and a "Suggest
 * Improvements for the Team" button that fills the Focus area column via
 * one batched AiAssistant::suggestTeamProductivityGaps() call. Admin/Manager
 * only — mirrors TeamPerformanceSummary::generate()'s exact guard, since the
 * parent page is already gated by ReportController::authorizePerformance().
 */
class ProductivityGapSuggestions extends Component
{
    public array $rows = [];

    public bool $aiEnabled = false;

    /** user_id => suggestion text. Ephemeral — never persisted. */
    public array $suggestions = [];

    public ?string $error = null;

    public function mount(array $rows): void
    {
        $this->rows = $rows;
        $this->aiEnabled = Ai::enabled();
    }

    public function generate(AiAssistant $ai): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $this->error = null;
        $result = $ai->suggestTeamProductivityGaps(collect($this->rows));

        if ($result === null) {
            $this->error = 'Could not generate suggestions right now. Please try again.';

            return;
        }

        $this->suggestions = collect($result)->pluck('suggestion', 'user_id')->all();
    }

    public function render()
    {
        $grouped = collect($this->rows)
            ->groupBy('role')
            ->map(fn ($group) => $group->sortBy(fn ($r) => $r['rank'] ?? PHP_INT_MAX)->values());

        return view('livewire.productivity-gap-suggestions', ['grouped' => $grouped]);
    }
}
