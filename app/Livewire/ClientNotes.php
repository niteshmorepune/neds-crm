<?php

namespace App\Livewire;

use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\Customer;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ClientNotes extends Component
{
    use RatesAiDrafts;

    public Customer $customer;

    public bool $canManage = false;

    #[Validate('required|string|max:5000')]
    public string $body = '';

    public bool $aiEnabled = false;

    /** Ephemeral AI summary shown in a dismissible panel (never persisted). */
    public ?string $summary = null;

    public ?int $summaryUsageId = null;

    public ?string $summaryFeedback = null;

    public function mount(Customer $customer, bool $canManage = false): void
    {
        $this->customer = $customer;
        $this->canManage = $canManage;
        $this->aiEnabled = Ai::enabled();
    }

    public function summarize(AiAssistant $assistant): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->can('view', $this->customer), 403);

        $this->summaryFeedback = null;
        $this->summary = $assistant->summarizeCustomer($this->customer)
            ?? 'Could not generate a summary right now. Please try again.';
        $this->summaryUsageId = $assistant->lastUsageId;
    }

    public function rateSummary(string $direction): void
    {
        $this->recordAiFeedback($this->summaryUsageId, $direction);
        $this->summaryFeedback = $direction;
    }

    public function dismissSummary(): void
    {
        $this->summary = null;
        $this->summaryUsageId = null;
        $this->summaryFeedback = null;
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
