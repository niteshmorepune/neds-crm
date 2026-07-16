<?php

namespace App\Livewire;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Service;
use App\Models\User;
use App\Services\SalesPipelineMetrics;
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

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('viewAny', Deal::class), 403);
    }

    public function moveDeal(int $dealId, string $stage): void
    {
        $deal = Deal::visibleTo(auth()->user())->findOrFail($dealId);
        abort_unless(auth()->user()->can('update', $deal), 403);

        $target = DealStage::tryFrom($stage);
        if ($target === null) {
            return;
        }

        if (! $deal->moveToStage($target)) {
            $this->dispatch('deal-move-blocked');
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

        $metrics = app(SalesPipelineMetrics::class);

        return view('livewire.deals-board', [
            'columns' => DealStage::columns(),
            'dealsByStage' => $deals,
            'kpis' => $metrics->kpis(auth()->user()),
            'stageConversion' => $metrics->stageConversion(auth()->user()),
            'customers' => Customer::query()->visibleTo(auth()->user())->orderBy('company_name')->get(['id', 'company_name']),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
