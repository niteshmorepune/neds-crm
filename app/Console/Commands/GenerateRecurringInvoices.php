<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'app:generate-recurring-invoices';

    protected $description = 'Generate invoices for due recurring templates and email them (run daily).';

    public function handle(InvoiceNumberGenerator $numbers): int
    {
        $today = Carbon::today();
        $generated = 0;

        RecurringInvoice::due($today)->with(['items', 'customer'])->get()->each(function (RecurringInvoice $template) use ($numbers, $today, &$generated) {
            $issueDate = $today->copy();

            $invoice = DB::transaction(function () use ($template, $numbers, $issueDate) {
                $invoice = Invoice::create([
                    'invoice_number' => $numbers->generate($issueDate),
                    'financial_year' => $numbers->financialYear($issueDate),
                    'customer_id' => $template->customer_id,
                    'status' => InvoiceStatus::Sent->value,
                    'issue_date' => $issueDate->toDateString(),
                    'due_date' => $issueDate->copy()->addDays(15)->toDateString(),
                    'place_of_supply_state_code' => $template->customer->state_code,
                    'discount' => $template->discount,
                ]);

                foreach ($template->items as $item) {
                    $invoice->items()->create([
                        'description' => $item->description,
                        'sac_code' => $item->sac_code,
                        'quantity' => $item->quantity,
                        'rate' => $item->rate,
                        'gst_rate' => $item->gst_rate,
                        'amount' => (int) round(((float) $item->quantity) * (int) $item->rate),
                        'sort_order' => $item->sort_order,
                    ]);
                }

                $invoice->refresh()->recalculateTotals();

                return $invoice;
            });

            // Advance schedule; deactivate once past the end date.
            $next = $template->frequency->advance($template->next_run_on);
            $template->next_run_on = $next;
            if ($template->end_date && $next->gt($template->end_date)) {
                $template->is_active = false;
            }
            $template->save();

            if ($email = $template->customer->billingEmail()) {
                Mail::to($email)->send(new InvoiceIssued($invoice));
            }

            $generated++;
        });

        $this->info("Generated {$generated} recurring invoice(s).");

        return self::SUCCESS;
    }
}
