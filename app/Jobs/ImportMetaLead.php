<?php

namespace App\Jobs;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Service;
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
 * utm_campaign stores the ad's human-readable name (falling back to the lead
 * form's name, then the raw ad_id/form_id if both name lookups fail) via one
 * extra best-effort Graph API call — see fetchCampaignLabel(). That call is
 * never allowed to block lead creation: any failure just falls back to the
 * raw id, same as before this was added.
 *
 * Custom form questions beyond the standard fields are opportunistically
 * mapped too, so Meta leads score as well as manually-entered ones: an
 * answer matching an active Service name (exact, case-insensitive) sets
 * service_id, and a "budget"-keyed answer with a parseable number sets
 * estimated_value. Anything that doesn't match either is preserved as a
 * note, same as before. See matchServiceId()/matchBudget().
 *
 * Also cross-checked against Lead::findOpenByPhone() before creating a new
 * row — Meta's Lead Ad flow auto-sends a WhatsApp message on the
 * submitter's behalf right after the form submit, which
 * WhatsappWebhookController would otherwise land as a second, separate lead
 * a few seconds apart from this one. See attachToExistingLead().
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
            // Meta's default response for this node omits ad_id/form_id
            // entirely (only id + field_data come back without an explicit
            // fields param) — confirmed live: every Meta lead so far has a
            // null utm_campaign because of this. field_data must stay
            // listed too, since requesting `fields` returns ONLY the named
            // fields, not the previous defaults plus extras.
            $response = Http::timeout(15)->get("https://graph.facebook.com/{$version}/{$this->leadgenId}", [
                'fields' => 'field_data,ad_id,form_id',
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
        [$serviceId, $extra] = $this->matchServiceId($extra);
        [$estimatedValue, $extra] = $this->matchBudget($extra);

        $adId = $response->json('ad_id');
        $formId = $response->json('form_id');
        $campaignLabel = $this->fetchCampaignLabel($version, $accessToken, $adId, $formId) ?? $adId ?? $formId;

        $existingLead = filled($fields['phone']) ? Lead::findOpenByPhone($fields['phone']) : null;

        if ($existingLead !== null) {
            $this->attachToExistingLead($existingLead, $campaignLabel, $serviceId, $estimatedValue, $extra);

            return;
        }

        $lead = Lead::create([
            'name' => $fields['name'] ?: 'Facebook Lead',
            'company' => $fields['company'],
            'email' => $fields['email'],
            'phone' => $fields['phone'],
            'source' => LeadSource::MetaAds->value,
            'service_id' => $serviceId,
            'estimated_value' => $estimatedValue,
            'status' => LeadStatus::New->value,
            'owner_id' => null,
            'meta_leadgen_id' => $this->leadgenId,
            'utm_source' => 'meta',
            'utm_medium' => 'paid_social',
            'utm_campaign' => $campaignLabel,
        ]);

        if ($extra !== []) {
            $body = collect($extra)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
            $lead->notes()->create(['user_id' => null, 'body' => "Additional form answers:\n{$body}"]);
        }
    }

    /**
     * A different channel already captured this same real-world enquiry as
     * an open lead (most commonly: Meta's Lead Ad flow auto-sends a WhatsApp
     * message right after the Instant Form submit, which
     * WhatsappWebhookController lands as a lead a few seconds before this
     * job runs) — attach this submission as a note instead of creating a
     * second lead.
     *
     * Attribution (source/utm_*) is corrected to Meta Ads here, even when a
     * different channel created the lead first: a real leadgen_id fetched
     * from Meta's API is definitive proof the person submitted the ad form,
     * which is stronger signal than "which webhook happened to arrive
     * first." (An earlier version of this method deliberately left source
     * alone to avoid Lead Source Performance flip-flopping — that produced
     * a real lead, id 235, genuinely Meta-sourced but permanently mislabeled
     * "WhatsApp" on its own page, with no way to fix it short of a
     * root-cause correction here.) service_id/estimated_value/utm_campaign
     * are only backfilled when the existing lead doesn't already have them.
     *
     * @param  array<string, string>  $extra
     */
    private function attachToExistingLead(Lead $lead, ?string $campaignLabel, ?int $serviceId, ?int $estimatedValue, array $extra): void
    {
        if ($lead->meta_leadgen_id === null) {
            $lead->update(['meta_leadgen_id' => $this->leadgenId]);
        }

        $fill = array_filter([
            'source' => $lead->source !== LeadSource::MetaAds ? LeadSource::MetaAds->value : null,
            'utm_source' => $lead->utm_source === null ? 'meta' : null,
            'utm_medium' => $lead->utm_medium === null ? 'paid_social' : null,
            'utm_campaign' => $lead->utm_campaign === null ? $campaignLabel : null,
            'service_id' => $lead->service_id === null ? $serviceId : null,
            'estimated_value' => $lead->estimated_value === null ? $estimatedValue : null,
        ], fn ($v) => $v !== null);

        if ($fill !== []) {
            $lead->update($fill);
        }

        $body = 'Also submitted a Meta Ads form'.($campaignLabel ? " (Campaign: {$campaignLabel})" : '').'.';

        if ($extra !== []) {
            $body .= "\n\nAdditional form answers:\n".collect($extra)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
        }

        $lead->notes()->create(['user_id' => null, 'body' => $body]);
    }

    /**
     * Best-effort lookup of a human-readable label for the Lead Source
     * Performance report's campaign breakdown — tries the ad's own name
     * first (what the advertiser actually named it in Ads Manager, e.g.
     * "SEO - Pune - July V2"), then the lead form's name if there's no ad_id
     * (organic/non-ad form submissions), and returns null on any failure so
     * the caller falls back to the raw id. Never throws — a broken or rate
     * limited call here must not stop the lead itself from being created.
     */
    private function fetchCampaignLabel(string $version, string $accessToken, ?string $adId, ?string $formId): ?string
    {
        $nodeId = $adId ?? $formId;

        if ($nodeId === null) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get("https://graph.facebook.com/{$version}/{$nodeId}", [
                'fields' => 'name',
                'access_token' => $accessToken,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Meta campaign name fetch exception', ['node_id' => $nodeId, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $name = $response->json('name');

        return is_string($name) && $name !== '' ? $name : null;
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

    /**
     * Matches a custom question's answer against an active Service name
     * (exact, case-insensitive) — this only works if the advertiser's form
     * uses the service names verbatim as multiple-choice options, which is
     * why the ad-setup guidance tells them to do exactly that. Matched on
     * the answer value, not the field key, since Meta slugifies the key from
     * whatever question text the advertiser typed and isn't predictable.
     *
     * @param  array<string, string>  $extra
     * @return array{0: ?int, 1: array<string, string>}
     */
    private function matchServiceId(array $extra): array
    {
        $services = Service::active()->get(['id', 'name'])
            ->keyBy(fn (Service $service) => mb_strtolower(trim($service->name)));

        foreach ($extra as $key => $value) {
            $service = $services->get(mb_strtolower(trim($value)));

            if ($service !== null) {
                unset($extra[$key]);

                return [$service->id, $extra];
            }
        }

        return [null, $extra];
    }

    /**
     * Parses a rupee amount (as integer paise) out of a "budget"-labelled
     * custom question. Only fields whose key mentions "budget" are attempted
     * — matching on the value alone would misread any unrelated numeric
     * answer (e.g. "years in business: 5") as a budget. A single number is
     * taken as-is; two or more (a "10000-25000" style range) are averaged.
     * Anything with no usable number is left as a note untouched.
     *
     * @param  array<string, string>  $extra
     * @return array{0: ?int, 1: array<string, string>}
     */
    private function matchBudget(array $extra): array
    {
        foreach ($extra as $key => $value) {
            if (! str_contains(mb_strtolower($key), 'budget')) {
                continue;
            }

            preg_match_all('/(\d[\d,]*(?:\.\d+)?)\s*([kK])?/', $value, $matches, PREG_SET_ORDER);

            $numbers = array_map(
                fn (array $match) => (float) str_replace(',', '', $match[1]) * (($match[2] ?? '') !== '' ? 1000 : 1),
                $matches
            );

            if ($numbers === []) {
                continue;
            }

            $rupees = count($numbers) >= 2
                ? array_sum(array_slice($numbers, 0, 2)) / 2
                : $numbers[0];

            unset($extra[$key]);

            return [(int) round($rupees * 100), $extra];
        }

        return [null, $extra];
    }
}
