<?php

namespace App\Services;

use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Mail\PaymentReceived;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentRecordedNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single point of truth for turning a captured Razorpay payment into a real
 * `Payment` row — called from both the portal's synchronous verify endpoint
 * (immediate UX) and the async webhook job (reliable source of truth), same
 * "whichever fires first wins" idempotency pattern as ProvisionClientExternallyJob.
 */
class RazorpayPaymentRecorder
{
    public function record(Invoice $invoice, string $orderId, string $paymentId, int $amountPaise): ?Payment
    {
        $existing = Payment::where('gateway_payment_id', $paymentId)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            $payment = $invoice->payments()->create([
                'paid_on' => now()->toDateString(),
                'mode' => PaymentMode::Gateway,
                'reference' => "Razorpay {$paymentId}",
                'amount' => $amountPaise,
                'tds_amount' => 0,
                'recorded_by' => null,
                'gateway_order_id' => $orderId,
                'gateway_payment_id' => $paymentId,
            ]);
        } catch (QueryException $e) {
            // Unique constraint on gateway_payment_id — the other path (sync
            // verify vs. webhook) won the race between our existence check
            // and this insert. Not an error, just a duplicate delivery.
            return Payment::where('gateway_payment_id', $paymentId)->first();
        }

        $invoice->refreshPaymentStatus();

        $this->notifyStaff($invoice, $payment);
        $this->sendReceipt($invoice, $payment);

        return $payment;
    }

    private function notifyStaff(Invoice $invoice, Payment $payment): void
    {
        $notification = new PaymentRecordedNotification($invoice, $payment);
        $recipients = User::where('is_active', true)->withAnyRole(UserRole::Accounts)->get();

        $ownerId = $invoice->customer?->owner_id;
        if ($ownerId && ! $recipients->contains('id', $ownerId)) {
            $owner = User::find($ownerId);
            if ($owner) {
                $recipients->push($owner);
            }
        }

        $recipients->each(fn (User $u) => $u->notify($notification));
    }

    private function sendReceipt(Invoice $invoice, Payment $payment): void
    {
        try {
            $invoice->loadMissing('customer.contacts');
            $email = $invoice->customer?->contacts->where('is_primary', true)->first()?->email
                ?? $invoice->customer?->contacts->first()?->email;

            if ($email) {
                Mail::to($email)->send(new PaymentReceived($invoice, $payment));
            }
        } catch (\Throwable $e) {
            Log::warning('Razorpay payment receipt email failed', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
