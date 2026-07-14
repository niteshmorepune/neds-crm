<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\GstCalculator;
use App\Support\Money;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class InvoiceBuilder extends Component
{
    use AuthorizesRequests;

    public int $invoiceId;

    public int $customer_id;

    public ?string $due_date = null;

    public string $discount = '0'; // rupees

    public bool $is_gst_exempt = false;

    /** @var array<int, array{description:string, sac_code:?string, quantity:string, rate:string, gst_rate:string}> */
    public array $items = [];

    public function mount(Invoice $invoice): void
    {
        abort_unless($invoice->exists, 404);
        $this->authorize('update', $invoice);

        $this->invoiceId = $invoice->id;
        $this->customer_id = $invoice->customer_id;
        $this->due_date = $invoice->due_date?->toDateString();
        $this->discount = (string) Money::toRupees($invoice->discount);
        $this->is_gst_exempt = $invoice->is_gst_exempt;
        $this->items = $invoice->items->map(fn ($item) => [
            'description' => $item->description,
            'sac_code' => $item->sac_code,
            'quantity' => (string) $item->quantity,
            'rate' => (string) Money::toRupees($item->rate),
            'gst_rate' => (string) $item->gst_rate,
        ])->all();
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
        $invoice = Invoice::findOrFail($this->invoiceId);
        $this->authorize('update', $invoice);
        abort_unless($invoice->isEditable(), 403);

        $this->validate([
            'due_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.sac_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['required', 'numeric', 'min:0', 'max:28'],
        ]);

        DB::transaction(function () use ($invoice) {
            $invoice->fill([
                'due_date' => $this->due_date ?: null,
                'discount' => Money::toPaise($this->discount ?: 0) ?? 0,
                'is_gst_exempt' => $this->is_gst_exempt,
            ])->save();

            $invoice->items()->delete();
            foreach (array_values($this->items) as $sort => $item) {
                $rate = Money::toPaise($item['rate'] ?: 0) ?? 0;
                $quantity = (float) $item['quantity'];
                $invoice->items()->create([
                    'description' => $item['description'],
                    'sac_code' => $item['sac_code'] ?: null,
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'gst_rate' => (float) $item['gst_rate'],
                    'amount' => (int) round($quantity * $rate),
                    'sort_order' => $sort,
                ]);
            }

            $invoice->refresh()->recalculateTotals();
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Invoice saved.');
    }

    public function render()
    {
        return view('livewire.invoice-builder');
    }
}
