<?php

namespace App\Livewire;

use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\CallLog;
use App\Models\Task;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * A single "Draft with AI" button embedded on the Daily Report page. Fills
 * the plain (non-Livewire) summary textarea via a dispatched browser event
 * rather than owning the textarea itself, so the existing POST-based submit
 * form on that page needs no changes.
 */
class DailyReportAiDraft extends Component
{
    use RatesAiDrafts;

    public bool $aiEnabled = false;

    public ?int $draftUsageId = null;

    public ?string $draftFeedback = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->aiEnabled = Ai::enabled();
    }

    public function draft(AiAssistant $assistant): void
    {
        abort_unless(Ai::enabled(), 403);

        $this->error = null;
        $this->draftFeedback = null;
        $user = auth()->user();
        $today = Carbon::today();

        $completedTasks = Task::where('assignee_id', $user->id)
            ->where('status', 'done')
            ->whereDate('completed_at', $today)
            ->with('project')
            ->get();

        $callLogsToday = CallLog::where('user_id', $user->id)
            ->whereDate('called_at', $today)
            ->with('callable')
            ->get();

        $draft = $assistant->draftDailyReportSummary($user, $completedTasks, $callLogsToday);
        $this->draftUsageId = $assistant->lastUsageId;

        if ($draft === null) {
            $this->error = $completedTasks->isEmpty() && $callLogsToday->isEmpty()
                ? 'Nothing logged yet today to draft from — complete a task or log a call first.'
                : 'Could not draft a summary right now. Please try again.';

            return;
        }

        $this->dispatch('daily-report-drafted', text: $draft);
    }

    public function rateDraft(string $direction): void
    {
        $this->recordAiFeedback($this->draftUsageId, $direction);
        $this->draftFeedback = $direction;
    }

    public function render()
    {
        return view('livewire.daily-report-ai-draft');
    }
}
