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

    public function mount(Project $project): void
    {
        $this->project = $project;
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
    }

    public function discard(int $noteId): void
    {
        abort_unless(auth()->user()?->can('update', $this->project), 403);

        $note = $this->pendingDrafts()->firstWhere('id', $noteId);
        abort_unless($note !== null, 404);

        $note->delete();
        unset($this->editedBody[$noteId]);
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
