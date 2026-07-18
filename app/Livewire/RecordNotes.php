<?php

namespace App\Livewire;

use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\Lead;
use App\Models\Note;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Generic notes timeline for any model with a polymorphic notes() relation
 * (leads, deals, …). Write access is gated by the record's "update" policy.
 */
class RecordNotes extends Component
{
    use RatesAiDrafts;

    public Model $record;

    public bool $canManage = false;

    /** Allows adding notes without full edit rights (e.g. Support on a project). */
    public bool $canAddNotes = false;

    public bool $showPortalToggle = false;

    #[Validate('required|string|max:5000')]
    public string $body = '';

    public bool $visibleToClient = false;

    // Edit state
    public ?int $editingNoteId = null;

    public string $editBody = '';

    public bool $editVisibleToClient = false;

    public ?int $draftUsageId = null;

    public ?string $draftFeedback = null;

    public function mount(Model $record, bool $canManage = false, bool $canAddNotes = false, bool $showPortalToggle = false): void
    {
        $this->record = $record;
        $this->canManage = $canManage;
        $this->canAddNotes = $canAddNotes;
        $this->showPortalToggle = $showPortalToggle;
        $this->visibleToClient = $showPortalToggle; // default ON when portal toggle is shown
    }

    /** AI follow-up drafting is offered on leads only, when AI is on and the user can write. */
    public function canDraft(): bool
    {
        return Ai::enabled() && $this->canManage && $this->record instanceof Lead;
    }

    /**
     * Draft a follow-up message into the note box. Editable; nothing is sent.
     */
    public function draftFollowUp(AiAssistant $assistant): void
    {
        abort_unless($this->canDraft() && auth()->user()?->can('update', $this->record), 403);

        $this->draftFeedback = null;

        if ($draft = $assistant->draftLeadFollowUp($this->record)) {
            $this->body = $draft;
            $this->draftUsageId = $assistant->lastUsageId;
        } else {
            $this->addError('body', 'Could not draft a follow-up right now. Please try again.');
        }
    }

    public function rateDraft(string $direction): void
    {
        $this->recordAiFeedback($this->draftUsageId, $direction);
        $this->draftFeedback = $direction;
    }

    public function addNote(): void
    {
        abort_unless($this->canManage || $this->canAddNotes, 403);

        $this->validate();

        $this->record->notes()->create([
            'user_id' => auth()->id(),
            'body' => $this->body,
            'visible_to_client' => $this->showPortalToggle && $this->visibleToClient,
        ]);

        $this->reset(['body', 'draftUsageId', 'draftFeedback']);
        $this->visibleToClient = $this->showPortalToggle;
    }

    public function startEdit(int $noteId): void
    {
        abort_unless($this->canManage || $this->canAddNotes, 403);

        $note = Note::findOrFail($noteId);
        // Only the author or a manager can edit a note.
        abort_unless($note->user_id === auth()->id() || $this->canManage, 403);

        $this->editingNoteId = $noteId;
        $this->editBody = $note->body;
        $this->editVisibleToClient = $note->visible_to_client;
    }

    public function cancelEdit(): void
    {
        $this->editingNoteId = null;
        $this->editBody = '';
        $this->editVisibleToClient = false;
    }

    public function updateNote(): void
    {
        abort_unless($this->canManage || $this->canAddNotes, 403);
        abort_unless($this->editingNoteId !== null, 422);

        $this->validate(['editBody' => 'required|string|max:5000']);

        $note = Note::findOrFail($this->editingNoteId);
        abort_unless($note->user_id === auth()->id() || $this->canManage, 403);

        $note->update([
            'body' => $this->editBody,
            'visible_to_client' => $this->showPortalToggle && $this->editVisibleToClient,
        ]);

        $this->editingNoteId = null;
        $this->editBody = '';
        $this->editVisibleToClient = false;
    }

    public function deleteNote(int $noteId): void
    {
        abort_unless($this->canManage || $this->canAddNotes, 403);

        $note = Note::findOrFail($noteId);
        abort_unless($note->user_id === auth()->id() || $this->canManage, 403);

        $note->delete();
    }

    public function render()
    {
        return view('livewire.record-notes', [
            'notes' => $this->record->notes()->with('author')->latest()->get(),
            'canDraft' => $this->canDraft(),
        ]);
    }
}
