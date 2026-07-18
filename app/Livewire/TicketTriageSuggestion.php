<?php

namespace App\Livewire;

use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Component;

/**
 * "Suggest triage" button on the new-ticket form. The subject/description/
 * customer_id it acts on live in the surrounding PLAIN (non-Livewire) form —
 * Alpine reads their current values at click time and passes them in, and
 * the result is pushed back out via a dispatched browser event the form
 * listens for to fill its own priority/service/assignee selects. Nothing is
 * ever applied automatically; the fields stay fully editable before submit.
 */
class TicketTriageSuggestion extends Component
{
    use RatesAiDrafts;

    public bool $aiEnabled = false;

    public ?string $reason = null;

    public ?string $priorityLabel = null;

    public ?string $serviceName = null;

    public ?string $assigneeName = null;

    public ?string $error = null;

    public ?int $suggestionUsageId = null;

    public ?string $suggestionFeedback = null;

    public function mount(): void
    {
        $this->aiEnabled = Ai::enabled();
    }

    public function suggest(AiAssistant $ai, ?int $customerId, string $subject, string $description): void
    {
        abort_unless(Ai::enabled() && auth()->user()?->can('create', Ticket::class), 403);

        $this->reset(['reason', 'priorityLabel', 'serviceName', 'assigneeName', 'error', 'suggestionUsageId', 'suggestionFeedback']);

        if (trim($subject) === '' || trim($description) === '') {
            $this->error = 'Fill in the subject and description first.';

            return;
        }

        if ($customerId === null) {
            $this->error = 'Select a client first.';

            return;
        }

        $customer = Customer::find($customerId);

        if ($customer === null) {
            $this->error = 'Select a client first.';

            return;
        }

        $suggestion = $ai->suggestTicketTriage($customer, $subject, $description);

        if ($suggestion === null) {
            $this->error = 'Could not suggest a triage for this client right now.';

            return;
        }

        $assigneeId = null;
        $assigneeName = null;

        if ($suggestion['service_id'] !== null) {
            $contact = $customer->projects()->where('service_id', $suggestion['service_id'])->first()?->schedulingContact();
            $assigneeId = $contact?->id;
            $assigneeName = $contact?->name;
        }

        $this->reason = $suggestion['reason'];
        $this->priorityLabel = $suggestion['priority']->label();
        $this->serviceName = $suggestion['service_name'];
        $this->assigneeName = $assigneeName;
        $this->suggestionUsageId = $ai->lastUsageId;

        $this->dispatch(
            'triage-suggested',
            priority: $suggestion['priority']->value,
            serviceId: $suggestion['service_id'],
            assigneeId: $assigneeId,
        );
    }

    public function rateSuggestion(string $direction): void
    {
        $this->recordAiFeedback($this->suggestionUsageId, $direction);
        $this->suggestionFeedback = $direction;
    }

    public function render()
    {
        return view('livewire.ticket-triage-suggestion');
    }
}
