<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ClientNotes extends Component
{
    public Customer $customer;

    public bool $canManage = false;

    #[Validate('required|string|max:5000')]
    public string $body = '';

    public bool $aiEnabled = false;

    /** Ephemeral AI summary shown in a dismissible panel (never persisted). */
    public ?string $summary = null;

    public function mount(Customer $customer, bool $canManage = false): void
    {
        $this->customer = $customer;
        $this->canManage = $canManage;
        $this->aiEnabled = Ai::enabled();
    }

    public function summarize(AiAssistant $assistant): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->can('view', $this->customer), 403);

        $this->summary = $assistant->summarizeCustomer($this->customer)
            ?? 'Could not generate a summary right now. Please try again.';
    }

    public function dismissSummary(): void
    {
        $this->summary = null;
    }

    public function addNote(): void
    {
        abort_unless(auth()->user()?->can('manage', $this->customer), 403);

        $this->validate();

        // @mentions are kept as plain text per spec — no parsing.
        $this->customer->notes()->create([
            'user_id' => auth()->id(),
            'body' => $this->body,
        ]);

        $this->reset('body');
    }

    public function render()
    {
        return view('livewire.client-notes', [
            'notes' => $this->customer->notes()->with('author')->get(),
        ]);
    }
}
