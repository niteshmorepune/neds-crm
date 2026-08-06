<?php

namespace App\Livewire;

use App\Enums\DeliverableStatus;
use App\Models\Project;
use Livewire\Component;

class ProjectDeliverables extends Component
{
    public Project $project;

    public bool $canManage = false;

    public string $title = '';

    public string $instructions = '';

    public function mount(Project $project, bool $canManage = false): void
    {
        $this->project = $project;
        $this->canManage = $canManage;
    }

    public function addDeliverable(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->project->deliverables()->create([
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['title', 'instructions']);
    }

    public function updateStatus(int $deliverableId, string $status): void
    {
        $this->authorizeManage();
        abort_unless(in_array($status, DeliverableStatus::values(), true), 422);

        $deliverable = $this->project->deliverables()->findOrFail($deliverableId);
        $deliverable->update(['status' => $status]);
    }

    public function removeDeliverable(int $deliverableId): void
    {
        $this->authorizeManage();

        $deliverable = $this->project->deliverables()->findOrFail($deliverableId);
        // Delete attachments individually (not a bulk query delete) so each
        // one's own deleting hook fires and cleans up its stored file.
        $deliverable->attachments->each->delete();
        $deliverable->delete();
    }

    public function render()
    {
        return view('livewire.project-deliverables', [
            'deliverables' => $this->project->deliverables()->with(['attachments', 'creator'])->latest()->get(),
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage, 403);
    }
}
