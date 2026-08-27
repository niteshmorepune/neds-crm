<?php

namespace App\Jobs;

use App\Models\Quotation;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Step 5 of the post-payment conversion pipeline (though this fires for
 * EVERY quotation sent through the CRM, not just VA-originated ones) —
 * the WhatsApp half of "Send Quotation", dispatched from
 * QuotationController::send() alongside the existing email send. The PDF
 * itself is never attached over WhatsApp (wadesk.in's template contract
 * has no document-header support — same reasoning as the VA audit
 * report); the button instead links to Quotation::publicPdfUrl(), a
 * permanent, unguessable-token public view link.
 *
 * No-ops (logs, never throws) whenever wadesk config, the template name,
 * or the customer's phone is unset — same discipline as
 * SendWhatsappHandoffMessageJob. Uses the Marketing line: a quotation send
 * is still a sales motion (chasing a deal to close), same line every
 * other touch in this pipeline uses, not the post-Won Support line.
 */
class SendQuotationWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $quotationId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');
        $marketingNumber = (string) config('services.wadesk.marketing_number');
        $templateName = (string) config('services.wadesk.quotation_sent_template_name');

        if (! $baseUrl || ! $serviceKey || ! $marketingNumber || ! $templateName) {
            return;
        }

        $quotation = Quotation::find($this->quotationId);

        if ($quotation === null) {
            return;
        }

        $quotation->loadMissing('customer.primaryContact');
        $phone = $quotation->customer?->billingPhone();

        if (blank($phone)) {
            return;
        }

        $digits = Phone::digits($phone);
        $name = $quotation->customer->primaryContact?->name ?: $quotation->customer->company_name;

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send-template", [
                    'phone' => $digits,
                    'businessNumber' => $marketingNumber,
                    'templateName' => $templateName,
                    'variables' => [$name],
                    'buttonUrlParam' => $this->tokenFor($quotation),
                ]);

            if (! $response->successful()) {
                Log::warning('SendQuotationWhatsAppJob: wadesk.in returned non-2xx', [
                    'quotation_id' => $this->quotationId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendQuotationWhatsAppJob: HTTP call to wadesk.in failed', [
                'quotation_id' => $this->quotationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * publicViewUrl() lazily generates+persists public_token on first call —
     * we only need the token itself here (it's what Meta appends), not the
     * full URL that method returns.
     */
    private function tokenFor(Quotation $quotation): string
    {
        $quotation->publicViewUrl();

        return $quotation->public_token;
    }
}
