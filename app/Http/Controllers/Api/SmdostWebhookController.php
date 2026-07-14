<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ContentPiece;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Notifications\SmdostBriefApproved;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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
            'brief_id' => ['required', 'string'],
            'brief_title' => ['required', 'string', 'max:255'],
            'scheduled_month' => ['required', 'string', 'max:7'],  // "YYYY-MM"
            'post_count' => ['required', 'integer', 'min:1'],
        ]);

        $customer = Customer::where('smdost_client_id', $data['smdost_client_id'])->first();

        if (! $customer) {
            return response()->json(['status' => 'no_customer_match']);
        }

        $invoice = DB::transaction(function () use ($data, $customer) {
            $issueDate = Carbon::now();
            $dueDate = Carbon::now()->addDays(30);
            $stateCode = $customer->state_code ?? '27';
            $month = Carbon::parse($data['scheduled_month'].'-01')->format('M Y');

            $invoice = Invoice::create([
                'invoice_number' => null,
                'financial_year' => $this->numbers->financialYear($issueDate),
                'customer_id' => $customer->id,
                'status' => InvoiceStatus::Draft->value,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'place_of_supply_state_code' => $stateCode,
                'is_intra_state' => $stateCode === '27',
                'is_gst_exempt' => $customer->gst_exempt,
                'subtotal' => 0,
                'discount' => 0,
                'taxable_total' => 0,
                'cgst_total' => 0,
                'sgst_total' => 0,
                'igst_total' => 0,
                'round_off' => 0,
                'total' => 0,
                'amount_paid' => 0,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Social Media Content — {$data['brief_title']} ({$month})",
                'sac_code' => '998361',
                'quantity' => 1,
                'rate' => 0,
                'gst_rate' => 18,
                'amount' => 0,
                'sort_order' => 1,
            ]);

            // Recalculate so GST fields are consistent (all zero at this stage,
            // but is_intra_state is set correctly by recalculateTotals()).
            $invoice->refresh()->recalculateTotals();

            return $invoice->fresh();
        });

        // Notify all accounts and admin users so they know to price and send.
        $recipients = User::withAnyRole(UserRole::Accounts, UserRole::Admin)->get();
        Notification::send($recipients, new SmdostBriefApproved($invoice, $customer, $data['brief_title']));

        Activity::create([
            'user_id' => null,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'event' => 'updated',
            'changes' => [
                'smdost_brief_approved' => $data['brief_id'],
                'draft_invoice_created' => $invoice->id,
            ],
        ]);

        return response()->json(['status' => 'created', 'invoice_id' => $invoice->id]);
    }

    /**
     * Called by socialmediadost.com when a content piece's copy is finalised
     * and ready to go to the partner agency for creative (images/video).
     * Creates a neds_led ContentPiece (status: sent_to_partner) on the
     * matching project so the team can track it and generate an upload link.
     */
    public function contentReady(Request $request): JsonResponse
    {
        // SMDost platform strings → CRM ContentPlatform enum values
        $platformMap = [
            'instagram' => 'instagram',
            'facebook' => 'facebook',
            'linkedin' => 'linkedin',
            'twitter' => 'twitter',
            'youtube' => 'youtube',
            'google business' => 'google_business',
            'gbp' => 'google_business',
            'tiktok' => 'other',
        ];

        $data = $request->validate([
            'smdost_client_id' => ['required', 'string'],
            'smdost_content_id' => ['required', 'string'],
            'platform' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'copy_text' => ['required', 'string'],
            'publish_date' => ['nullable', 'date'],
        ]);

        // Idempotency — if this content piece was already synced, return the existing record.
        $existing = ContentPiece::where('smdost_content_id', $data['smdost_content_id'])->first();
        if ($existing) {
            return response()->json(['status' => 'already_exists', 'content_piece_id' => $existing->id]);
        }

        $customer = Customer::where('smdost_client_id', $data['smdost_client_id'])->first();
        if (! $customer) {
            return response()->json(['status' => 'no_customer_match']);
        }

        // Prefer an active social-media / GMB project; fall back to most recent active.
        $project = $customer->projects()
            ->where('status', 'active')
            ->with('service')
            ->latest()
            ->get()
            ->sortByDesc(function ($p) {
                $name = strtolower($p->service?->name ?? '');

                return str_contains($name, 'social') || str_contains($name, 'gmb') ? 1 : 0;
            })
            ->first();

        if (! $project) {
            Log::warning('[SMDost content-ready] No active project for client', [
                'smdost_client_id' => $data['smdost_client_id'],
            ]);

            return response()->json(['status' => 'no_project_found'], 422);
        }

        $platform = $platformMap[strtolower($data['platform'])] ?? 'other';
        $createdBy = $project->owner_id
            ?? User::where('role', UserRole::Admin->value)->value('id');

        $piece = ContentPiece::create([
            'project_id' => $project->id,
            'workflow_type' => ContentWorkflowType::NedsLed->value,
            'platform' => $platform,
            'status' => ContentStatus::SentToPartner->value,
            'title' => $data['title'],
            'copy_text' => $data['copy_text'],
            'publish_date' => $data['publish_date'] ?? null,
            'smdost_content_id' => $data['smdost_content_id'],
            'created_by' => $createdBy,
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'event' => 'updated',
            'changes' => [
                'smdost_content_synced' => $data['smdost_content_id'],
                'content_piece_id' => $piece->id,
            ],
        ]);

        return response()->json(['status' => 'created', 'content_piece_id' => $piece->id], 201);
    }
}
