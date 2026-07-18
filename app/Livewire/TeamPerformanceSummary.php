<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Services\AiAssistant;
use App\Services\ReportMetrics;
use App\Services\SalesPipelineMetrics;
use App\Support\Ai;
use Illuminate\Support\Carbon;
use Livewire\Component;

class TeamPerformanceSummary extends Component
{
    public string $fromDate;

    public string $toDate;

    public bool $aiEnabled = false;

    /** Ephemeral AI summary shown in a dismissible panel (never persisted). */
    public ?string $summary = null;

    public function mount(string $fromDate, string $toDate): void
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->aiEnabled = Ai::enabled();
    }

    /**
     * Admin/Manager only — mirrors ReportController::authorizePerformance().
     * This commentary is never shown to the employees it's about.
     */
    public function generate(ReportMetrics $metrics, SalesPipelineMetrics $pipeline, AiAssistant $ai): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $from = Carbon::parse($this->fromDate)->startOfDay();
        $to = Carbon::parse($this->toDate)->endOfDay();
        $rows = $metrics->employeePerformance($from, $to);
        $dwellTimes = $pipeline->repStageDwellTimes();

        $this->summary = $ai->summarizeTeamPerformance($rows, $from, $to, $dwellTimes)
            ?? 'Could not generate a summary right now. Please try again.';
    }

    public function dismiss(): void
    {
        $this->summary = null;
    }

    public function render()
    {
        return view('livewire.team-performance-summary');
    }
}
