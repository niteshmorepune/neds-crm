<?php

namespace App\Livewire;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\AiAssistant;
use Livewire\Component;

/**
 * "Status may need updating" suggestion on the lead show page — embedded
 * only when Lead::hasStaleNewStatus() is true (see LeadController::show()'s
 * gating). Mirrors DealLostReasonField's shape: a manual "Suggest" trigger
 * (never auto-fires on page load — same on-demand AI convention every other
 * button in this app uses), a pre-selected but overridable status, and a
 * separate Apply that only this component controls. Once applied, the lead
 * is no longer New, so hasStaleNewStatus() goes false and a page refresh
 * (redirect back to itself) naturally drops this component from the page.
 */
class LeadStatusSuggestion extends Component
{
    public Lead $lead;

    public ?string $suggestedStatus = null;

    public ?string $selectedStatus = null;

    public ?string $rationale = null;

    /** Guards against a second Claude call if suggest() is somehow triggered twice. */
    public bool $requested = false;

    public function mount(Lead $lead): void
    {
        $this->lead = $lead;
    }

    public function suggest(AiAssistant $assistant): void
    {
        abort_unless(auth()->user()?->can('update', $this->lead), 403);

        if ($this->requested) {
            return;
        }

        $this->requested = true;

        $result = $assistant->suggestLeadStatusUpdate($this->lead);

        if ($result !== null) {
            $this->suggestedStatus = $result['status']?->value;
            $this->selectedStatus = $this->suggestedStatus;
            $this->rationale = $result['rationale'];
        }
    }

    public function apply(): void
    {
        abort_unless(auth()->user()?->can('update', $this->lead), 403);

        $status = LeadStatus::tryFrom((string) $this->selectedStatus);

        if (! in_array($status, [LeadStatus::Contacted, LeadStatus::Qualified, LeadStatus::Lost], true)) {
            $this->addError('selectedStatus', 'Pick a status first.');

            return;
        }

        $this->lead->update(['status' => $status->value]);

        // Full navigation (not a partial update) so the plain-Blade "Status:"
        // line and every other status-driven bit of the page — which this
        // component doesn't own — reflect the change immediately.
        session()->flash('status', "Status updated to {$status->label()}.");
        $this->redirect(route('leads.show', $this->lead), navigate: false);
    }

    public function render()
    {
        return view('livewire.lead-status-suggestion', [
            'statuses' => [LeadStatus::Contacted, LeadStatus::Qualified, LeadStatus::Lost],
        ]);
    }
}
