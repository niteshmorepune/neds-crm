<?php

namespace App\Livewire;

use App\Livewire\Concerns\RatesAiDrafts;
use App\Models\Customer;
use App\Services\AiAssistant;
use App\Support\Ai;
use Livewire\Component;

/**
 * Renders a Sales rep's own "who to call today" list
 * (CallPriorityService::rankedClients()) plus an on-demand, per-row
 * "Suggest a talking point" button. Mirrors ClientRadarSuggestion's
 * on-demand-per-customer shape, but list-shaped (one component covers every
 * row) rather than one component instance per row, since there's no
 * per-row flag data that needs isolating the way Client Radar's does.
 */
class CallPriorityList extends Component
{
    use RatesAiDrafts;

    /** @var array<int, array{customer_id: int, company_name: string, days_since_contact: int, follow_up_due: bool, top_stage_label: ?string, top_stage_probability: ?int, score: float, reason: string}> */
    public array $rows = [];

    public bool $aiEnabled = false;

    /** customer_id => suggestion text. Ephemeral — never persisted. */
    public array $suggestions = [];

    /** customer_id => ai_usages id, for the thumbs up/down. */
    public array $usageIds = [];

    /** customer_id => 'up'|'down'. */
    public array $feedback = [];

    public ?string $error = null;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function mount(array $rows): void
    {
        $this->rows = $rows;
        $this->aiEnabled = Ai::enabled();
    }

    public function suggestTalkingPoint(int $customerId, AiAssistant $ai): void
    {
        abort_unless(Ai::enabled(), 403);

        $row = collect($this->rows)->first(fn (array $r) => $r['customer_id'] === $customerId);
        abort_if($row === null, 404);

        $customer = Customer::findOrFail($customerId);

        // Defense-in-depth: mount() already scoped $rows to the viewer's own
        // book (CallPriorityService::rankedClients() filters by owner_id), so
        // this should never actually trip — but never trust a client-sent id.
        abort_unless($customer->owner_id === auth()->id(), 403);

        $this->error = null;
        $suggestion = $ai->suggestCallTalkingPoint($customer, $this->signalsFor($row));

        if ($suggestion === null) {
            $this->error = 'Could not generate a talking point right now. Please try again.';

            return;
        }

        $this->suggestions[$customerId] = $suggestion;
        $this->usageIds[$customerId] = $ai->lastUsageId;
    }

    public function rate(int $customerId, string $direction): void
    {
        $this->recordAiFeedback($this->usageIds[$customerId] ?? null, $direction);
        $this->feedback[$customerId] = $direction;
    }

    public function render()
    {
        return view('livewire.call-priority-list');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, array{label: string, detail: string}>
     */
    private function signalsFor(array $row): array
    {
        $signals = [
            'last_contact' => [
                'label' => 'Last Contact',
                'detail' => "{$row['days_since_contact']} day(s) ago",
            ],
        ];

        if ($row['follow_up_due']) {
            $signals['follow_up_due'] = [
                'label' => 'Follow-up Due',
                'detail' => 'A scheduled follow-up on an open deal is due or overdue',
            ];
        }

        if ($row['top_stage_label'] !== null) {
            $signals['deal_stage'] = [
                'label' => 'Deal Stage',
                'detail' => "{$row['top_stage_label']} ({$row['top_stage_probability']}% likelihood)",
            ];
        }

        return $signals;
    }
}
