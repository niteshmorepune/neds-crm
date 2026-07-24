<?php

namespace App\Livewire;

use App\Services\AiAssistant;
use App\Services\ReportMetrics;
use App\Support\Ai;
use Livewire\Component;

/**
 * Private "your own productivity" widget — embedded on the Sales/Support/
 * Accounts/Intern dashboard partials only (never Admin/Manager, who aren't
 * ranked participants in ReportMetrics::rankedEmployeePerformance()). Only
 * ever computes/shows the VIEWER'S OWN row — the same guarantee every other
 * per-role dashboard stat method (salesStats($user), internStats($user))
 * already relies on, so no dedicated Policy is needed here either.
 */
class MyProductivity extends Component
{
    public ?array $row = null;

    public bool $aiEnabled = false;

    /** Ephemeral — never persisted. */
    public ?string $tip = null;

    public ?string $error = null;

    public function mount(ReportMetrics $metrics): void
    {
        $this->aiEnabled = Ai::enabled();

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $this->row = $metrics->rankedEmployeePerformance($from, $to)
            ->firstWhere('user_id', auth()->id());
    }

    public function getTip(AiAssistant $ai): void
    {
        abort_unless($this->row !== null && $this->row['score'] !== null, 403);

        $this->error = null;
        $this->tip = $ai->suggestProductivityImprovement($this->row)
            ?? 'Could not generate a tip right now. Please try again.';
    }

    public function render()
    {
        return view('livewire.my-productivity');
    }
}
