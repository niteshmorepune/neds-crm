<?php

namespace App\Livewire;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
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

        return view('livewire.deals-board', [
            'columns' => DealStage::columns(),
            'dealsByStage' => $deals,
            'kpis' => $this->kpis(auth()->user()),
            'customers' => Customer::query()->visibleTo(auth()->user())->orderBy('company_name')->get(['id', 'company_name']),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'owners' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * KPI strip figures for the Sales Pipeline header. Scoped by the same
     * visibleTo() rule as the board itself, so Sales reps see their own
     * numbers and Admin/Manager see the whole pipeline — unlike the
     * company-wide, date-ranged win rate/avg deal size/avg cycle in
     * BusinessOverviewMetrics::pipelineFunnel() (Reports > Pipeline &
     * Funnel), which can't be reused here for that reason. Whole-number
     * rounding matches that report so the same-named figures don't imply
     * different precision.
     */
    private function kpis(User $user): array
    {
        $openByStage = Deal::query()
            ->visibleTo($user)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->selectRaw('stage, COALESCE(SUM(value), 0) as value')
            ->groupBy('stage')
            ->pluck('value', 'stage');

        $weightedForecast = 0;
        foreach ($openByStage as $stageValue => $sum) {
            $weightedForecast += (int) round($sum * DealStage::from($stageValue)->probability() / 100);
        }

        $closedCounts = Deal::query()
            ->visibleTo($user)
            ->whereIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');
        $wonCount = (int) ($closedCounts[DealStage::Won->value] ?? 0);
        $lostCount = (int) ($closedCounts[DealStage::Lost->value] ?? 0);

        $wonValues = Deal::query()->visibleTo($user)->where('stage', DealStage::Won->value)->pluck('value');

        $wonWithCycle = Deal::query()
            ->visibleTo($user)
            ->where('stage', DealStage::Won->value)
            ->whereNotNull('won_at')
            ->get(['created_at', 'won_at']);

        $now = now();
        $fyStartYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyStart = Carbon::create($fyStartYear, 4, 1)->startOfDay();

        return [
            'open_pipeline_value' => (int) $openByStage->sum(),
            'weighted_forecast' => $weightedForecast,
            'won_this_month_value' => (int) Deal::query()
                ->visibleTo($user)
                ->where('stage', DealStage::Won->value)
                ->whereNotNull('won_at')
                ->where('won_at', '>=', $now->copy()->startOfMonth())
                ->sum('value'),
            'won_this_fy_value' => (int) Deal::query()
                ->visibleTo($user)
                ->where('stage', DealStage::Won->value)
                ->whereNotNull('won_at')
                ->where('won_at', '>=', $fyStart)
                ->sum('value'),
            'win_rate' => ($wonCount + $lostCount) > 0 ? (int) round($wonCount / ($wonCount + $lostCount) * 100) : null,
            'avg_deal_size' => $wonValues->isNotEmpty() ? (int) round($wonValues->avg()) : null,
            'avg_sales_cycle_days' => $wonWithCycle->isNotEmpty()
                ? (int) round($wonWithCycle->avg(fn (Deal $deal) => $deal->created_at->diffInDays($deal->won_at)))
                : null,
        ];
    }
}
