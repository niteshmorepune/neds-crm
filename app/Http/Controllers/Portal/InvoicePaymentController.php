<?php

namespace App\Http\Controllers\Portal;

use App\Services\RazorpayClient;
use App\Services\RazorpayPaymentRecorder;
use App\Support\RazorpaySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicePaymentController extends PortalController
{
    public function order(int $invoice, RazorpayClient $razorpay): JsonResponse
    {
        // Scoped to the contact's own customer — findOrFail 404s on another's invoice.
        $invoice = $this->customer()->invoices()->findOrFail($invoice);

        if (! $invoice->status->isPayable() || $invoice->balance() <= 0) {
            return response()->json(['message' => 'This invoice is not payable online.'], 422);
        }

        if (! $razorpay->configured()) {
            return response()->json(['message' => 'Online payment is not available right now.'], 503);
        }

        $order = $razorpay->createOrder(
            $invoice->balance(),
            $invoice->invoice_number ?: "invoice-{$invoice->id}",
            ['invoice_id' => (string) $invoice->id, 'invoice_number' => (string) $invoice->invoice_number],
        );

        if ($order === null) {
            return response()->json(['message' => 'Could not start the payment. Please try again shortly.'], 502);
        }

        $contact = auth('portal')->user();

        return response()->json([
            'order_id' => $order['id'],
            'amount' => $invoice->balance(),
            'key_id' => config('services.razorpay.key_id'),
            'invoice_number' => $invoice->invoice_number,
            'company_name' => config('company.name'),
            'contact_name' => $contact?->name,
            'contact_email' => $contact?->email,
            'contact_phone' => $contact?->phone,
        ]);
    }

    public function verify(Request $request, int $invoice, RazorpayClient $razorpay, RazorpayPaymentRecorder $recorder): JsonResponse
    {
        $invoice = $this->customer()->invoices()->findOrFail($invoice);

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

        if ($order === null || (string) ($order['notes']['invoice_id'] ?? '') !== (string) $invoice->id) {
            return response()->json(['message' => 'Payment could not be verified.'], 422);
        }

        $recorder->record($invoice, $data['razorpay_order_id'], $data['razorpay_payment_id'], (int) $order['amount']);

        return response()->json(['status' => 'ok']);
    }
}
