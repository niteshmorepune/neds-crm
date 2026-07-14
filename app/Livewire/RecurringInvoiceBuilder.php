<?php

namespace App\Livewire;

use App\Enums\RecurringFrequency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RecurringInvoiceBuilder extends Component
{
    use AuthorizesRequests;

    public ?int $recurringId = null;

    public ?int $customer_id = null;

    public ?int $service_id = null;

    public string $frequency = 'monthly';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public string $discount = '0';

    public bool $is_gst_exempt = false;

    public string $terms = '';

    /** @var array<int, array<string, string>> */
    public array $items = [];

    public function mount(?RecurringInvoice $recurring = null): void
    {
        $this->authorize('create', Invoice::class);

        if ($recurring && $recurring->exists) {
            $this->recurringId = $recurring->id;
            $this->customer_id = $recurring->customer_id;
            $this->service_id = $recurring->service_id;
            $this->frequency = $recurring->frequency->value;
            $this->start_date = $recurring->start_date->toDateString();
            $this->end_date = $recurring->end_date?->toDateString();
            $this->discount = (string) Money::toRupees($recurring->discount);
            $this->is_gst_exempt = $recurring->is_gst_exempt;
            $this->terms = (string) $recurring->terms;
            $this->items = $recurring->items->map(fn ($i) => [
                'description' => $i->description, 'sac_code' => $i->sac_code,
                'quantity' => (string) $i->quantity, 'rate' => (string) Money::toRupees($i->rate),
                'gst_rate' => (string) $i->gst_rate,
            ])->all();
        } else {
            $this->start_date = now()->startOfMonth()->toDateString();
            $this->addItem();
        }
    }

    /** Defaults the non-GST checkbox from the selected client, same as QuotationBuilder. */
    public function updatedCustomerId(?int $value): void
    {
        $this->is_gst_exempt = $value ? (bool) Customer::find($value)?->gst_exempt : false;
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'sac_code' => '', 'quantity' => '1', 'rate' => '0', 'gst_rate' => '18'];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function save()
    {
        $this->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'frequency' => ['required', Rule::enum(RecurringFrequency::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'min:0', 'max:28'],
        ]);

        $recurring = DB::transaction(function () {
            $recurring = $this->recurringId
                ? RecurringInvoice::findOrFail($this->recurringId)
                : new RecurringInvoice(['is_active' => true]);

            $recurring->fill([
                'customer_id' => $this->customer_id,
                'service_id' => $this->service_id,
                'frequency' => $this->frequency,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date ?: null,
                'discount' => Money::toPaise($this->discount ?: 0) ?? 0,
                'is_gst_exempt' => $this->is_gst_exempt,
                'terms' => $this->terms ?: null,
            ]);

            if (! $recurring->exists || $recurring->invoices()->doesntExist()) {
                // Advance from the start date until we reach a future date, so
                // a historical start date never leaves next_run_on in the past.
                // On edit we also recalculate when no invoices have been generated
                // yet — the cycle hasn't started so it's safe to adjust.
                $next = Carbon::parse($this->start_date)->startOfDay();
                $today = now()->startOfDay();
                while ($next->lt($today)) {
                    $next = $recurring->frequency->advance($next);
                }
                $recurring->next_run_on = $next;
            }
            $recurring->save();

            $recurring->items()->delete();
            foreach (array_values($this->items) as $sort => $item) {
                $recurring->items()->create([
                    'description' => $item['description'],
                    'sac_code' => $item['sac_code'] ?: null,
                    'quantity' => (float) $item['quantity'],
                    'rate' => Money::toPaise($item['rate'] ?: 0) ?? 0,
                    'gst_rate' => (float) $item['gst_rate'],
                    'sort_order' => $sort,
                ]);
            }

            return $recurring;
        });

        return redirect()->route('recurring-invoices.index')
            ->with('status', 'Recurring invoice saved.');
    }

    public function render()
    {
        return view('livewire.recurring-invoice-builder', [
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'frequencies' => RecurringFrequency::cases(),
        ]);
    }
}
