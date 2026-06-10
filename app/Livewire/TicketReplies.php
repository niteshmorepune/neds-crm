<?php

namespace App\Livewire;

use App\Mail\TicketNotification;
use App\Models\Ticket;
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

    public function mount(Ticket $ticket, bool $canManage = false): void
    {
        $this->ticket = $ticket;
        $this->canManage = $canManage;
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

        $this->reset(['body', 'is_internal']);
    }

    public function render()
    {
        return view('livewire.ticket-replies', [
            'replies' => $this->ticket->replies()->with(['author', 'contact'])->get(),
        ]);
    }
}
