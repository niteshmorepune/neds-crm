<?php

namespace App\Livewire;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\TaskAssigned;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Component;

/**
 * "Suggest onboarding tasks" button on a project's page — AI-drafted
 * ADDITIONS to the standard per-service checklist CreateOnboardingTasks
 * already created, grounded in this deal's notes/quotation line items.
 * Opt-in only, per the standing "no task flood" rule: suggest() never
 * creates anything, it only populates a review list; a Task is only ever
 * created when the user ticks a suggestion and clicks addSelected().
 */
class OnboardingTaskSuggestions extends Component
{
    use RatesAiDrafts;

    public Project $project;

    public bool $aiEnabled = false;

    /** @var list<array{title: string, description: string, due_in_days: int, selected: bool}> */
    public array $suggestions = [];

    public bool $hasSuggested = false;

    public ?string $error = null;

    public ?int $suggestionUsageId = null;

    public ?string $suggestionFeedback = null;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->aiEnabled = Ai::enabled();
    }

    public function suggest(AiAssistant $ai): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->can('update', $this->project), 403);

        $this->reset(['suggestions', 'error', 'suggestionUsageId', 'suggestionFeedback']);
        $this->hasSuggested = true;

        $result = $ai->suggestOnboardingTasks($this->project);

        if ($result === null) {
            $this->error = 'Could not suggest onboarding tasks right now. Please try again.';

            return;
        }

        $this->suggestions = array_map(fn (array $s) => [...$s, 'selected' => true], $result);
        $this->suggestionUsageId = $this->suggestions !== [] ? $ai->lastUsageId : null;
    }

    public function addSelected(): void
    {
        abort_unless(auth()->user()?->can('update', $this->project), 403);

        $toAdd = collect($this->suggestions)->filter(fn (array $s) => $s['selected']);

        if ($toAdd->isEmpty()) {
            return;
        }

        $assignee = $this->project->assignees->first(fn ($u) => $u->role === UserRole::Support)
            ?? $this->project->assignees->firstWhere('pivot.role', 'lead')
            ?? $this->project->owner;

        $creatorId = auth()->id();

        foreach ($toAdd as $item) {
            $task = Task::create([
                'title' => $item['title'],
                'description' => $item['description'],
                'project_id' => $this->project->id,
                'assignee_id' => $assignee?->id,
                'created_by' => $creatorId,
                'due_date' => now()->addDays($item['due_in_days'])->toDateString(),
                'priority' => 'normal',
                'status' => TaskStatus::Todo->value,
            ]);

            if ($assignee && $assignee->id !== $creatorId) {
                $assignee->notify(new TaskAssigned($task));
            }
        }

        $count = $toAdd->count();

        // Full navigation (not a partial update) so the plain-Blade Tasks
        // list below — which this component doesn't own — picks up the
        // newly created rows, and the flash message reads correctly on the
        // freshly rendered page.
        session()->flash('status', $count === 1 ? '1 task added.' : "{$count} tasks added.");
        $this->redirect(route('projects.show', $this->project), navigate: false);
    }

    public function rateSuggestion(string $direction): void
    {
        $this->recordAiFeedback($this->suggestionUsageId, $direction);
        $this->suggestionFeedback = $direction;
    }

    public function render()
    {
        return view('livewire.onboarding-task-suggestions');
    }
}
