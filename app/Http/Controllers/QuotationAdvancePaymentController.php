<?php

namespace App\Http\Controllers;

use App\Actions\GenerateMilestoneInvoice;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Services\RazorpayClient;
use App\Services\RazorpayPaymentRecorder;
use App\Support\RazorpaySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Step 6 of the post-payment VA conversion pipeline (though this applies to
 * every milestone-billed quotation sent through the CRM, not just
 * VA-originated ones): lets a client pay a quotation's next milestone
 * online, from the public quotation-view page (QuotationPublicController),
 * no login required — authorized purely by knowing the unguessable
 * public_token, same as every other public-token flow in this app.
 *
 * Mirrors Portal\InvoicePaymentController::order()/verify() almost exactly
 * (same Razorpay Orders API + Checkout.js flow, same sync-verify-for-
 * immediate-UX pattern), with one difference: the Portal flow pays an
 * INVOICE THAT ALREADY EXISTS; here, no invoice exists yet when the client
 * clicks "Pay Advance" — order() creates it on the spot (via
 * GenerateMilestoneInvoice::handleOrExisting(), the same prorated-GST
 * billing logic the staff-facing Milestone Manager "Generate invoice"
 * button already uses) and puts its id in the Razorpay order notes, exactly
 * like the Portal flow does. That means the EXISTING invoice-payment
 * webhook (RazorpayWebhookController / RecordGatewayPaymentJob) already
 * handles this payment type with zero changes — it only ever looks at
 * notes.invoice_id, which by this point already points at a real invoice
 * either way.
 */
class QuotationAdvancePaymentController extends Controller
{
    public function order(string $token, RazorpayClient $razorpay, GenerateMilestoneInvoice $generator): JsonResponse
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();
        $milestone = $quotation->nextPayableMilestone();

        if ($milestone === null) {
            return response()->json(['message' => 'No advance payment is currently due for this quotation.'], 422);
        }

        if (! $razorpay->configured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $invoice = $generator->handleOrExisting($milestone);

        if (! $invoice->status->isPayable() || $invoice->balance() <= 0) {
            return response()->json(['message' => 'This milestone has already been paid.'], 422);
        }

        $order = $razorpay->createOrder(
            $invoice->balance(),
            $invoice->invoice_number ?: "invoice-{$invoice->id}",
            ['invoice_id' => (string) $invoice->id, 'invoice_number' => (string) $invoice->invoice_number],
        );

        if ($order === null) {
            return response()->json(['message' => 'Could not start the payment. Please try again shortly.'], 502);
        }

        $quotation->loadMissing('customer.primaryContact');

        return response()->json([
            'order_id' => $order['id'],
            'amount' => $invoice->balance(),
            'key_id' => config('services.razorpay.key_id'),
            'invoice_number' => $invoice->invoice_number,
            'milestone_title' => $milestone->title,
            'company_name' => config('company.name'),
            'contact_name' => $quotation->customer->primaryContact?->name,
            'contact_email' => $quotation->customer->billingEmail(),
            'contact_phone' => $quotation->customer->billingPhone(),
        ]);
    }

    public function verify(Request $request, string $token, RazorpayClient $razorpay, RazorpayPaymentRecorder $recorder): JsonResponse
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $secret = (string) config('services.razorpay.key_secret');

        $valid = RazorpaySignature::verifyPayment(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
            $secret,
        );

        if (! $valid) {
            return response()->json(['message' => 'Payment could not be verified.'], 422);
        }

        // Re-fetch the order from Razorpay directly for the authoritative
        // amount/invoice — never trust a client-supplied figure for money.
        $order = $razorpay->fetchOrder($data['razorpay_order_id']);
        $invoiceId = (int) ($order['notes']['invoice_id'] ?? 0);
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

        // Defense: the invoice this order actually paid must genuinely be a
        // milestone invoice belonging to THIS quotation — never trust the
        // token holder to only ever ask about their own order.
        $belongsToQuotation = $invoice !== null
            && $quotation->milestones()->where('invoice_id', $invoice->id)->exists();

        if ($order === null || $invoice === null || ! $belongsToQuotation) {
            return response()->json(['message' => 'Payment could not be verified.'], 422);
        }

        $recorder->record($invoice, $data['razorpay_order_id'], $data['razorpay_payment_id'], (int) $order['amount']);

        return response()->json(['status' => 'ok']);
    }
}
