<?php

namespace App\Livewire;

use App\Models\Lead;
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
    public Model $record;

    public bool $canManage = false;

    public bool $showPortalToggle = false;

    #[Validate('required|string|max:5000')]
    public string $body = '';

    public bool $visibleToClient = false;

    public function mount(Model $record, bool $canManage = false, bool $showPortalToggle = false): void
    {
        $this->record = $record;
        $this->canManage = $canManage;
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

        if ($draft = $assistant->draftLeadFollowUp($this->record)) {
            $this->body = $draft;
        } else {
            $this->addError('body', 'Could not draft a follow-up right now. Please try again.');
        }
    }

    public function addNote(): void
    {
        abort_unless(auth()->user()?->can('update', $this->record), 403);

        $this->validate();

        $this->record->notes()->create([
            'user_id' => auth()->id(),
            'body' => $this->body,
            'visible_to_client' => $this->showPortalToggle && $this->visibleToClient,
        ]);

        $this->reset('body');
        $this->visibleToClient = $this->showPortalToggle;
    }

    public function render()
    {
        return view('livewire.record-notes', [
            'notes' => $this->record->notes()->with('author')->get(),
            'canDraft' => $this->canDraft(),
        ]);
    }
}
