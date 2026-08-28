<?php

namespace App\Livewire;

use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Component;

/**
 * "✨ Get call brief" button on the Log a Call form (calls/create.blade.php)
 * — only rendered when the form was reached with a lead_id/customer_id
 * preselected (clicking "Log a call" from that record's own page), the same
 * moment CallTimingMetrics' "best time to call" hint appears on the same
 * page. That hint answers WHEN to call; this answers WHAT TO SAY. See
 * AiAssistant::prepareCallBrief() for how this differs from the existing
 * "who to call today" Call Priority talking-point suggestion.
 */
class CallBrief extends Component
{
    use RatesAiDrafts;

    public ?Lead $lead = null;

    public ?Customer $customer = null;

    public bool $aiEnabled = false;

    /** Ephemeral — never persisted. */
    public ?string $brief = null;

    public ?int $briefUsageId = null;

    public ?string $briefFeedback = null;

    public ?string $error = null;

    public function mount(?int $leadId = null, ?int $customerId = null): void
    {
        $this->lead = $leadId ? Lead::find($leadId) : null;
        $this->customer = $this->lead === null && $customerId ? Customer::find($customerId) : null;
        $this->aiEnabled = Ai::enabled();
    }

    public function canGenerate(): bool
    {
        if (! $this->aiEnabled) {
            return false;
        }

        $record = $this->lead ?? $this->customer;

        return $record !== null && auth()->user()?->can('view', $record);
    }

    public function generate(AiAssistant $assistant): void
    {
        abort_unless($this->canGenerate(), 403);

        $this->error = null;
        $this->briefFeedback = null;

        $brief = $assistant->prepareCallBrief($this->lead ?? $this->customer);

        if ($brief === null) {
            $this->error = 'Could not generate a brief right now. Please try again.';

            return;
        }

        $this->brief = $brief;
        $this->briefUsageId = $assistant->lastUsageId;
    }

    public function rate(string $direction): void
    {
        $this->recordAiFeedback($this->briefUsageId, $direction);
        $this->briefFeedback = $direction;
    }

    public function dismiss(): void
    {
        $this->brief = null;
        $this->briefUsageId = null;
        $this->briefFeedback = null;
    }

    public function render()
    {
        return view('livewire.call-brief');
    }
}
