<?php

namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends the "welcome to support" WhatsApp template to a newly-won client on
 * the support line, via wadesk.in's POST /api/send-template — the client may
 * never have messaged the support number before, so there's no existing
 * conversation for the ordinary /api/send endpoint to attach to.
 *
 * No-ops (logs, never throws) whenever wadesk config or the handoff template
 * name is unset — the template must be submitted and approved in Meta
 * Business Manager first; this job is safe to ship before that happens and
 * starts working the moment WADESK_HANDOFF_TEMPLATE_NAME is set, no code
 * change needed. Same failure-tolerance discipline as SendWhatsappReplyJob —
 * a wadesk.in outage or missing template must never block a Deal being won.
 */
class SendWhatsappHandoffMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $customerId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');
        $supportNumber = (string) config('services.wadesk.support_number');
        $templateName = (string) config('services.wadesk.handoff_template_name');

        if (! $baseUrl || ! $serviceKey || ! $supportNumber || ! $templateName) {
            return;
        }

        $customer = Customer::find($this->customerId);

        if ($customer === null || blank($customer->phone)) {
            return;
        }

        // wadesk.in stores contact phone digits-only, no leading "+" —
        // matches WhatsappWebhookController::findCustomer's own normalization.
        $digits = preg_replace('/\D/', '', $customer->phone);

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send-template", [
                    'phone' => $digits,
                    'businessNumber' => $supportNumber,
                    'templateName' => $templateName,
                    // Matches the template's {{1}} — Meta's classifier
                    // rejected a fully-static version of this template as
                    // Marketing-leaning, so the approved template body
                    // greets the client by (company) name.
                    'variables' => [$customer->company_name],
                ]);

            if (! $response->successful()) {
                Log::warning('SendWhatsappHandoffMessageJob: wadesk.in returned non-2xx', [
                    'customer_id' => $this->customerId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendWhatsappHandoffMessageJob: HTTP call to wadesk.in failed', [
                'customer_id' => $this->customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
