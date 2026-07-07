<?php

namespace App\Jobs;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches one Meta (Facebook/Instagram) Lead Ads submission via the Graph API
 * and creates a Lead from it. The webhook event only carries a leadgen_id —
 * this job does the follow-up call to get the actual name/email/phone the
 * person submitted.
 *
 * Idempotent on leads.meta_leadgen_id (Meta's webhook can redeliver the same
 * event). The Graph API call is try/catched — a failure here must never
 * break the webhook or leave the queue stuck; it's logged and swallowed.
 *
 * utm_source/medium are set to a fixed 'meta'/'paid_social' label since Meta's
 * webhook payload doesn't distinguish Facebook vs Instagram placement.
 * utm_campaign stores the raw ad_id (falling back to form_id) — Meta's basic
 * leadgen response doesn't include human-readable campaign/ad names; mapping
 * those would need an extra Graph API call this job deliberately doesn't make
 * until the mapping's been verified against a real payload.
 */
class ImportMetaLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public string $leadgenId) {}

    public function handle(): void
    {
        if (Lead::where('meta_leadgen_id', $this->leadgenId)->exists()) {
            return;
        }

        $accessToken = (string) config('services.meta.page_access_token');

        if ($accessToken === '') {
            Log::warning('Meta lead import skipped: no page access token configured', [
                'leadgen_id' => $this->leadgenId,
            ]);

            return;
        }

        $version = (string) config('services.meta.graph_api_version', 'v19.0');

        try {
            $response = Http::timeout(15)->get("https://graph.facebook.com/{$version}/{$this->leadgenId}", [
                'access_token' => $accessToken,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Meta lead fetch exception', ['leadgen_id' => $this->leadgenId, 'error' => $e->getMessage()]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('Meta lead fetch failed', [
                'leadgen_id' => $this->leadgenId,
                'status' => $response->status(),
            ]);

            return;
        }

        // A redelivered webhook could race a previous run of this job past
        // the check above — the unique column is the real guard.
        if (Lead::where('meta_leadgen_id', $this->leadgenId)->exists()) {
            return;
        }

        [$fields, $extra] = $this->parseFieldData($response->json('field_data', []));

        $lead = Lead::create([
            'name' => $fields['name'] ?: 'Facebook Lead',
            'company' => $fields['company'],
            'email' => $fields['email'],
            'phone' => $fields['phone'],
            'source' => LeadSource::MetaAds->value,
            'status' => LeadStatus::New->value,
            'owner_id' => null,
            'meta_leadgen_id' => $this->leadgenId,
            'utm_source' => 'meta',
            'utm_medium' => 'paid_social',
            'utm_campaign' => $response->json('ad_id') ?? $response->json('form_id'),
        ]);

        if ($extra !== []) {
            $body = collect($extra)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
            $lead->notes()->create(['user_id' => null, 'body' => "Additional form answers:\n{$body}"]);
        }
    }

    /**
     * Flattens Meta's field_data [{name, values:[...]}] shape and maps the
     * common standard field names to name/email/phone/company. Anything
     * unrecognised (custom questions the advertiser added) is returned
     * separately so it can be preserved as a note rather than dropped.
     *
     * @param  array<int, array{name?: string, values?: array<int, string>}>  $fieldData
     * @return array{0: array{name: ?string, email: ?string, phone: ?string, company: ?string}, 1: array<string, string>}
     */
    private function parseFieldData(array $fieldData): array
    {
        $flat = [];
        foreach ($fieldData as $field) {
            $key = $field['name'] ?? null;
            $value = $field['values'][0] ?? null;
            if ($key !== null && $value !== null) {
                $flat[$key] = $value;
            }
        }

        $name = $flat['full_name']
            ?? (trim(($flat['first_name'] ?? '').' '.($flat['last_name'] ?? '')) ?: null);

        $mapped = [
            'name' => $name,
            'email' => $flat['email'] ?? $flat['work_email'] ?? null,
            'phone' => $flat['phone_number'] ?? $flat['work_phone_number'] ?? null,
            'company' => $flat['company_name'] ?? null,
        ];

        $usedKeys = ['full_name', 'first_name', 'last_name', 'email', 'work_email', 'phone_number', 'work_phone_number', 'company_name'];
        $extra = collect($flat)->except($usedKeys)->all();

        return [$mapped, $extra];
    }
}
