<?php

namespace App\Livewire;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Service;
use App\Services\GstCalculator;
use App\Services\InvoiceNumberGenerator;
use App\Support\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuotationBuilder extends Component
{
    use AuthorizesRequests;

    public ?int $quotationId = null;

    public ?int $customer_id = null;

    public ?int $deal_id = null;

    public ?string $validity_date = null;

    public string $terms = '';

    public string $discount = '0'; // rupees

    public bool $is_gst_exempt = false;

    /** @var array<int, array{description:string, sac_code:?string, quantity:string, rate:string, gst_rate:string}> */
    public array $items = [];

    public function mount(?Quotation $quotation = null, ?int $customer_id = null, ?int $deal_id = null): void
    {
        if ($quotation && $quotation->exists) {
            $this->authorize('update', $quotation);

            $this->quotationId = $quotation->id;
            $this->customer_id = $quotation->customer_id;
            $this->deal_id = $quotation->deal_id;
            $this->validity_date = $quotation->validity_date?->toDateString();
            $this->terms = (string) $quotation->terms;
            $this->discount = (string) Money::toRupees($quotation->discount);
            $this->is_gst_exempt = $quotation->is_gst_exempt;
            $this->items = $quotation->items->map(fn ($item) => [
                'description' => $item->description,
                'sac_code' => $item->sac_code,
                'quantity' => (string) $item->quantity,
                'rate' => (string) Money::toRupees($item->rate),
                'gst_rate' => (string) $item->gst_rate,
            ])->all();
        } else {
            $this->authorize('create', Quotation::class);
            // mount() only receives route params; query-string values must be read from request()
            $this->customer_id = $customer_id ?? (request()->integer('customer_id') ?: null);
            $this->deal_id = $deal_id ?? (request()->integer('deal_id') ?: null);
            $this->is_gst_exempt = $this->customer_id
                ? (bool) Customer::find($this->customer_id)?->gst_exempt
                : false;
            $this->addItem();
        }
    }

    /**
     * Refresh the GST-exempt default whenever the client changes, mirroring
     * that client's own default — the team can still override the checkbox
     * afterward before saving.
     */
    public function updatedCustomerId(?int $value): void
    {
        $this->is_gst_exempt = $value ? (bool) Customer::find($value)?->gst_exempt : false;
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'sac_code' => '', 'quantity' => '', 'rate' => '', 'gst_rate' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if ($this->items === []) {
            $this->addItem();
        }
    }

    /**
     * Live GST preview from the current form state.
     *
     * @return array<string, mixed>
     */
    public function getTotalsProperty(): array
    {
        $lines = collect($this->items)->map(fn ($item) => [
            'quantity' => (float) ($item['quantity'] ?: 0),
            'rate' => Money::toPaise($item['rate'] ?: 0) ?? 0,
            'gst_rate' => (float) ($item['gst_rate'] ?: 0),
        ])->all();

        $customer = $this->customer_id ? Customer::find($this->customer_id) : null;

        return app(GstCalculator::class)->calculate(
            $lines,
            Money::toPaise($this->discount ?: 0) ?? 0,
            $customer?->state_code,
            $customer?->isOverseas() ?? false,
            $this->is_gst_exempt,
        );
    }

    public function save()
    {
        $this->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'deal_id' => ['nullable', Rule::exists('deals', 'id')],
            'validity_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.sac_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'min:0', 'max:28'],
        ]);

        $customer = Customer::findOrFail($this->customer_id);

        $quotation = DB::transaction(function () use ($customer) {
            $quotation = $this->quotationId
                ? Quotation::findOrFail($this->quotationId)
                : new Quotation(['status' => QuotationStatus::Draft->value]);

            $quotation->fill([
                'customer_id' => $customer->id,
                'deal_id' => $this->deal_id,
                'place_of_supply_state_code' => $customer->state_code,
                'discount' => Money::toPaise($this->discount ?: 0) ?? 0,
                'is_gst_exempt' => $this->is_gst_exempt,
                'terms' => $this->terms ?: null,
                'validity_date' => $this->validity_date ?: null,
            ])->save();

            // Replace line items.
            $quotation->items()->delete();
            foreach (array_values($this->items) as $sort => $item) {
                $rate = Money::toPaise($item['rate'] ?: 0) ?? 0;
                $quantity = (float) $item['quantity'];
                $quotation->items()->create([
                    'description' => $item['description'],
                    'sac_code' => $item['sac_code'] ?: null,
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'gst_rate' => (float) $item['gst_rate'],
                    'amount' => (int) round($quantity * $rate),
                    'sort_order' => $sort,
                ]);
            }

            if ($quotation->number === null) {
                $fy = app(InvoiceNumberGenerator::class)->financialYear(Carbon::now());
                $quotation->number = sprintf('QTN/%s/%04d', $fy, $quotation->id);
            }

            $quotation->save();
            $quotation->refresh()->recalculateTotals();

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('status', 'Quotation saved.');
    }

    public function render()
    {
        return view('livewire.quotation-builder', [
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name', 'state_code']),
            'services' => Service::active()->orderBy('sort_order')->get(),
        ]);
    }
}
