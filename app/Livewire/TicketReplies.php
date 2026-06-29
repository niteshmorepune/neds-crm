<?php

namespace App\Livewire;

use App\Jobs\SendWhatsappReplyJob;
use App\Mail\TicketNotification;
use App\Models\Ticket;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;

class TicketReplies extends Component
{
    public Ticket $ticket;

    public bool $canManage = false;

    #[Validate('required|string|max:5000')]
    public string $body = '';

    public bool $is_internal = false;

    public bool $aiEnabled = false;

    /** Ephemeral AI summary shown in a dismissible panel (never persisted). */
    public ?string $summary = null;

    public function mount(Ticket $ticket, bool $canManage = false): void
    {
        $this->ticket = $ticket;
        $this->canManage = $canManage;
        $this->aiEnabled = Ai::enabled();
    }

    /**
     * Draft a customer reply with AI and drop it into the editable box. The
     * staffer reviews and sends it manually — drafts are never auto-sent.
     */
    public function draftReply(AiAssistant $assistant): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->can('reply', $this->ticket), 403);

        if ($draft = $assistant->draftTicketReply($this->ticket->load('replies'))) {
            $this->body = $draft;
        } else {
            $this->addError('body', 'Could not draft a reply right now. Please try again.');
        }
    }

    public function summarize(AiAssistant $assistant): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->can('view', $this->ticket), 403);

        $this->summary = $assistant->summarizeTicket($this->ticket->load('replies'))
            ?? 'Could not generate a summary right now. Please try again.';
    }

    public function dismissSummary(): void
    {
        $this->summary = null;
    }

    public function addReply(): void
    {
        abort_unless(auth()->user()?->can('reply', $this->ticket), 403);

        $this->validate();

        $reply = $this->ticket->replies()->create([
            'user_id' => auth()->id(),
            'body' => $this->body,
            'is_internal' => $this->is_internal,
        ]);

        // Customer-visible replies notify the client; internal notes do not.
        if (! $reply->is_internal && ($email = $this->ticket->customer->billingEmail())) {
            Mail::to($email)->send(new TicketNotification($this->ticket, 'replied', $reply));
        }

        // Forward to wadesk.in for WhatsApp tickets so the customer receives
        // the reply on their WhatsApp without staff switching apps.
        if (! $reply->is_internal
            && $this->ticket->channel === 'whatsapp'
            && $this->ticket->whatsapp_conversation_id) {
            SendWhatsappReplyJob::dispatch($reply->id);
        }

        $this->reset(['body', 'is_internal']);
    }

    public function render()
    {
        return view('livewire.ticket-replies', [
            'replies' => $this->ticket->replies()->with(['author', 'contact'])->get(),
        ]);
    }
}
