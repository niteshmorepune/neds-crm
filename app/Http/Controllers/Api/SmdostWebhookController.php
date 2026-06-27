<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Notifications\SmdostBriefApproved;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SmdostWebhookController
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    /**
     * Called by socialmediadost.com when every piece of content in a brief is
     * approved. Creates a draft invoice for the accounts team to price and send.
     */
    public function briefApproved(Request $request): JsonResponse
    {
        $data = $request->validate([
            'smdost_client_id' => ['required', 'string'],
            'brief_id'         => ['required', 'string'],
            'brief_title'      => ['required', 'string', 'max:255'],
            'scheduled_month'  => ['required', 'string', 'max:7'],  // "YYYY-MM"
            'post_count'       => ['required', 'integer', 'min:1'],
        ]);

        $customer = Customer::where('smdost_client_id', $data['smdost_client_id'])->first();

        if (! $customer) {
            return response()->json(['status' => 'no_customer_match']);
        }

        $invoice = DB::transaction(function () use ($data, $customer) {
            $issueDate  = Carbon::now();
            $dueDate    = Carbon::now()->addDays(30);
            $stateCode  = $customer->state_code ?? '27';
            $month      = Carbon::parse($data['scheduled_month'].'-01')->format('M Y');

            $invoice = Invoice::create([
                'invoice_number'             => null,
                'financial_year'             => $this->numbers->financialYear($issueDate),
                'customer_id'                => $customer->id,
                'status'                     => InvoiceStatus::Draft->value,
                'issue_date'                 => $issueDate->toDateString(),
                'due_date'                   => $dueDate->toDateString(),
                'place_of_supply_state_code' => $stateCode,
                'is_intra_state'             => $stateCode === '27',
                'subtotal'                   => 0,
                'discount'                   => 0,
                'taxable_total'              => 0,
                'cgst_total'                 => 0,
                'sgst_total'                 => 0,
                'igst_total'                 => 0,
                'round_off'                  => 0,
                'total'                      => 0,
                'amount_paid'                => 0,
            ]);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => "Social Media Content — {$data['brief_title']} ({$month})",
                'sac_code'    => '998361',
                'quantity'    => 1,
                'rate'        => 0,
                'gst_rate'    => 18,
                'amount'      => 0,
                'sort_order'  => 1,
            ]);

            // Recalculate so GST fields are consistent (all zero at this stage,
            // but is_intra_state is set correctly by recalculateTotals()).
            $invoice->refresh()->recalculateTotals();

            return $invoice->fresh();
        });

        // Notify all accounts and admin users so they know to price and send.
        $recipients = User::whereIn('role', [UserRole::Accounts->value, UserRole::Admin->value])->get();
        Notification::send($recipients, new SmdostBriefApproved($invoice, $customer, $data['brief_title']));

        Activity::create([
            'user_id'      => null,
            'subject_type' => Customer::class,
            'subject_id'   => $customer->id,
            'event'        => 'updated',
            'changes'      => [
                'smdost_brief_approved' => $data['brief_id'],
                'draft_invoice_created' => $invoice->id,
            ],
        ]);

        return response()->json(['status' => 'created', 'invoice_id' => $invoice->id]);
    }
}
