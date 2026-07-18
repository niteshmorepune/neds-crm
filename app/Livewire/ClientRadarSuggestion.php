<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Component;

class ClientRadarSuggestion extends Component
{
    use RatesAiDrafts;

    public int $customerId;

    /** @var array<string, array{label: string, detail: string, ticket_id?: int}> */
    public array $flags;

    public bool $aiEnabled = false;

    /** Ephemeral AI suggestion shown in a dismissible panel (never persisted). */
    public ?string $suggestion = null;

    /** Set only when the row has a Low Satisfaction flag with a specific ticket behind it. */
    public ?int $lowSatisfactionTicketId = null;

    /** Ephemeral CSAT recovery message draft (never persisted). */
    public ?string $recoveryDraft = null;

    public ?int $suggestionUsageId = null;

    public ?string $suggestionFeedback = null;

    public ?int $recoveryUsageId = null;

    public ?string $recoveryFeedback = null;

    /**
     * @param  array<string, array{label: string, detail: string, ticket_id?: int}>  $flags
     */
    public function mount(int $customerId, array $flags): void
    {
        $this->customerId = $customerId;
        $this->flags = $flags;
        $this->aiEnabled = Ai::enabled();
        $this->lowSatisfactionTicketId = $flags['low_satisfaction']['ticket_id'] ?? null;
    }

    /**
     * Admin/Manager only — mirrors the client-radar.index page's own gating
     * (menu.access:client-radar), kept here too as defense-in-depth.
     */
    public function generate(AiAssistant $ai): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $customer = Customer::findOrFail($this->customerId);

        $this->suggestionFeedback = null;
        $this->suggestion = $ai->suggestClientAction($customer, $this->flags)
            ?? 'Could not generate a suggestion right now. Please try again.';
        $this->suggestionUsageId = $ai->lastUsageId;
    }

    public function rateSuggestion(string $direction): void
    {
        $this->recordAiFeedback($this->suggestionUsageId, $direction);
        $this->suggestionFeedback = $direction;
    }

    public function dismiss(): void
    {
        $this->suggestion = null;
        $this->suggestionUsageId = null;
        $this->suggestionFeedback = null;
    }

    public function draftRecovery(AiAssistant $ai): void
    {
        abort_unless(
            Ai::enabled() && $this->lowSatisfactionTicketId !== null && auth()->user()?->hasRole(UserRole::Admin, UserRole::Manager),
            403
        );

        $ticket = Ticket::with('customer')->findOrFail($this->lowSatisfactionTicketId);

        $this->recoveryFeedback = null;
        $this->recoveryDraft = $ai->draftCsatRecoveryMessage($ticket)
            ?? 'Could not draft a recovery message right now. Please try again.';
        $this->recoveryUsageId = $ai->lastUsageId;
    }

    public function rateRecovery(string $direction): void
    {
        $this->recordAiFeedback($this->recoveryUsageId, $direction);
        $this->recoveryFeedback = $direction;
    }

    public function dismissRecovery(): void
    {
        $this->recoveryDraft = null;
        $this->recoveryUsageId = null;
        $this->recoveryFeedback = null;
    }

    public function render()
    {
        return view('livewire.client-radar-suggestion');
    }
}
