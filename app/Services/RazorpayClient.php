<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plain REST client for Razorpay's Orders API (Basic Auth: key_id:key_secret)
 * — no SDK, matches this app's Hostinger-safe precedent for every other
 * third-party integration (AnthropicClient, GoogleSpeechClient). Every method
 * returns null on any failure rather than throwing, so a Razorpay outage
 * never breaks the portal page — the caller decides how to surface that.
 */
class RazorpayClient
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    public function configured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    /**
     * @param  array<string, mixed>  $notes
     * @return array{id: string, amount: int, currency: string}|null
     */
    public function createOrder(int $amountPaise, string $receipt, array $notes = []): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = $this->http()->post(self::BASE_URL.'/orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => $notes,
                'payment_capture' => 1,
            ]);

            if (! $response->successful()) {
                Log::warning('Razorpay order creation failed', [
                    'status' => $response->status(),
                    'receipt' => $receipt,
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Razorpay order creation exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Re-fetches an order from Razorpay directly — used by the sync verify
     * path to get an authoritative amount/notes instead of trusting anything
     * the browser sent back.
     *
     * @return array{id: string, amount: int, notes: array<string, mixed>}|null
     */
    public function fetchOrder(string $orderId): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = $this->http()->get(self::BASE_URL."/orders/{$orderId}");

            if (! $response->successful()) {
                Log::warning('Razorpay order fetch failed', [
                    'status' => $response->status(),
                    'order_id' => $orderId,
                ]);

                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('Razorpay order fetch exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function http()
    {
        return Http::withBasicAuth(
            (string) config('services.razorpay.key_id'),
            (string) config('services.razorpay.key_secret'),
        )->timeout(15);
    }
}
