<?php

namespace App\Livewire;

use App\Mail\ProjectDailyUpdate as ProjectDailyUpdateMail;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * Lets a project owner (or admin/manager, per ProjectPolicy::update) review
 * AI-drafted daily client updates before they reach the client. Approving
 * flips the note visible_to_client=true (so it appears in the client portal
 * feed) and emails the client's billing contact; discarding deletes the
 * draft. Editable drafts, never auto-sent.
 */
class ProjectDailyUpdateReview extends Component
{
    public Project $project;

    /** @var array<int, string> */
    public array $editedBody = [];

    /** @var array<int, int> IDs of drafts checked via the per-draft/select-all checkboxes. */
    public array $selected = [];

    public function mount(Project $project): void
    {
        $this->project = $project;

        // Seed editedBody with each draft's real text up front — wire:model
        // binds the textarea to editedBody.{id}, and Livewire's client-side
        // hydration syncs the DOM to this property on load. Leaving an
        // entry unset here made the textarea render blank (see the
        // 2026-09-03 fix), even though the note's real body was intact in
        // the database the whole time.
        foreach ($this->pendingDrafts() as $draft) {
            $this->editedBody[$draft->id] = $draft->body;
        }
    }

    public function approve(int $noteId): void
    {
        abort_unless(auth()->user()?->can('update', $this->project), 403);

        $note = $this->pendingDrafts()->firstWhere('id', $noteId);
        abort_unless($note !== null, 404);

        $body = trim($this->editedBody[$noteId] ?? $note->body);
        abort_unless($body !== '', 422);

        $note->update([
            'body' => $body,
            'visible_to_client' => true,
        ]);

        if ($email = $this->project->customer?->billingEmail()) {
            Mail::to($email)->queue(new ProjectDailyUpdateMail($this->project, $note));
        }

        unset($this->editedBody[$noteId]);
        $this->selected = array_diff($this->selected, [$noteId]);

        // Lets pages that embed this component alongside other static,
        // non-Livewire content (e.g. the Approval Center's pending count)
        // know to refresh — this component's own DOM re-renders itself
        // regardless, this is purely for surrounding page state.
        $this->dispatch('approval-center-refresh');
    }

    public function discard(int $noteId): void
    {
        abort_unless(auth()->user()?->can('update', $this->project), 403);

        $note = $this->pendingDrafts()->firstWhere('id', $noteId);
        abort_unless($note !== null, 404);

        $note->delete();
        unset($this->editedBody[$noteId]);
        $this->selected = array_diff($this->selected, [$noteId]);

        $this->dispatch('approval-center-refresh');
    }

    public function toggleSelectAll(): void
    {
        $ids = $this->pendingDrafts()->pluck('id')->all();

        $this->selected = count(array_intersect($this->selected, $ids)) === count($ids)
            ? []
            : $ids;
    }

    public function discardSelected(): void
    {
        abort_unless(auth()->user()?->can('update', $this->project), 403);

        $ids = $this->pendingDrafts()->pluck('id')->intersect($this->selected)->all();
        abort_unless($ids !== [], 422);

        Note::whereIn('id', $ids)->delete();

        foreach ($ids as $id) {
            unset($this->editedBody[$id]);
        }
        $this->selected = array_diff($this->selected, $ids);

        $this->dispatch('approval-center-refresh');
    }

    private function pendingDrafts()
    {
        return $this->project->notes()
            ->where('ai_generated', true)
            ->where('visible_to_client', false)
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.project-daily-update-review', [
            'pendingDrafts' => $this->pendingDrafts(),
        ]);
    }
}
