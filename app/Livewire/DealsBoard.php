<?php

namespace App\Livewire;

use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Service;
use App\Models\User;
use App\Services\AiAssistant;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DealsBoard extends Component
{
    public bool $showAddForm = false;

    // Add-deal form
    public ?int $customer_id = null;

    public string $title = '';

    public ?int $service_id = null;

    public ?int $owner_id = null;

    public ?string $value = null; // rupees

    /** Set by suggestLostReason() while the Lost-reason picker is open. */
    public ?string $suggestedLostReason = null;

    public ?string $lostReasonRationale = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('viewAny', Deal::class), 403);
    }

    public function moveDeal(int $dealId, string $stage, ?string $lostReason = null): void
    {
        $deal = Deal::visibleTo(auth()->user())->findOrFail($dealId);
        abort_unless(auth()->user()->can('update', $deal), 403);

        $target = DealStage::tryFrom($stage);
        if ($target === null) {
            return;
        }

        $reason = $lostReason !== null ? DealLostReason::tryFrom($lostReason) : null;

        if (! $deal->moveToStage($target, $reason)) {
            $this->dispatch('deal-move-blocked');
        }
    }

    /**
     * Called the moment a card is dropped on the Lost column, before the rep
     * picks a reason — a draft-only suggestion, never auto-applied. Resets
     * to null first so a stale suggestion from a previously-opened deal
     * never briefly shows for a new one while this call is in flight.
     */
    public function suggestLostReason(int $dealId, AiAssistant $assistant): void
    {
        $this->suggestedLostReason = null;
        $this->lostReasonRationale = null;

        $deal = Deal::visibleTo(auth()->user())->find($dealId);

        if ($deal === null || ! auth()->user()->can('update', $deal)) {
            return;
        }

        $result = $assistant->suggestDealLostReason($deal);

        if ($result !== null) {
            $this->suggestedLostReason = $result['reason']?->value;
            $this->lostReasonRationale = $result['rationale'];
        }
    }

    public function createDeal(): void
    {
        abort_unless(auth()->user()?->can('create', Deal::class), 403);

        $validated = $this->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        Deal::create([
            'customer_id' => $validated['customer_id'],
            'title' => $validated['title'],
            'service_id' => $validated['service_id'],
            'owner_id' => $validated['owner_id'],
            'value' => Money::toPaise($validated['value']),
            'stage' => DealStage::New->value,
        ]);

        $this->reset(['showAddForm', 'customer_id', 'title', 'service_id', 'owner_id', 'value']);
    }

    public function render()
    {
        $deals = Deal::query()
            ->visibleTo(auth()->user())
            ->with(['customer', 'owner', 'service'])
            ->latest()
            ->get()
            ->groupBy(fn (Deal $deal) => $deal->stage->value);

        return view('livewire.deals-board', [
            'columns' => DealStage::columns(),
            'dealsByStage' => $deals,
            'customers' => Customer::query()->visibleTo(auth()->user())->orderBy('company_name')->get(['id', 'company_name']),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
