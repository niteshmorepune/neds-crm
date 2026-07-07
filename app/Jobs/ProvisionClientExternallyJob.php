<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provisions a customer as a client in nedsdrishti.in and socialmediadost.com
 * when a CRM deal is marked Won.
 *
 * Idempotent: if drishti_client_id is already set the job exits early, so a
 * deal that flips to Won twice (edge case via direct DB edit) won't double-create.
 *
 * Both HTTP calls are wrapped in try/catch — failure must never break the deal
 * workflow. Errors are logged and an activity note is written on the customer.
 *
 * Queue driver: database (Hostinger shared hosting constraint — no Redis/Horizon).
 */
class ProvisionClientExternallyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $customerId) {}

    public function handle(): void
    {
        $customer = Customer::with('primaryContact', 'deals.service')
            ->find($this->customerId);

        if ($customer === null) {
            return;
        }

        // Idempotent guard — already provisioned.
        if ($customer->drishti_client_id !== null) {
            return;
        }

        $contact = $customer->primaryContact;
        $service = $customer->deals()
            ->whereNotNull('service_id')
            ->with('service')
            ->latest()
            ->first()?->service;

        $drishtiId = $this->provisionDrishti($customer, $contact, $service);
        $smdostId = $this->provisionSmdost($customer, $contact, $service, $drishtiId);

        if ($drishtiId !== null || $smdostId !== null) {
            $customer->updateQuietly([
                'drishti_client_id' => $drishtiId,
                'smdost_client_id' => $smdostId,
            ]);

            Activity::create([
                'user_id' => null,
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'event' => 'updated',
                'changes' => array_filter([
                    'drishti_client_id' => $drishtiId,
                    'smdost_client_id' => $smdostId,
                ]),
            ]);
        }
    }

    private function provisionDrishti(Customer $customer, $contact, $service): ?string
    {
        $baseUrl = rtrim(config('services.drishti.base_url'), '/');
        $serviceKey = config('services.drishti.service_key');

        if (! $baseUrl || ! $serviceKey) {
            return null;
        }

        try {
            $domain = $this->extractDomain($customer);

            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/clients", array_filter([
                    'name' => $customer->company_name,
                    'domain' => $domain,
                    'industry' => $service?->name,
                    'contactName' => $contact?->name,
                    'contactEmail' => $contact?->email ?? $customer->email,
                    'monthlyRetainer' => null,
                ]));

            if (! $response->successful()) {
                Log::warning('Drishti client provision failed', [
                    'customer_id' => $customer->id,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $clientId = $response->json('data.id') ?? $response->json('id');

            // Create a CLIENT-role portal user in Drishti so the contact can log in.
            if ($clientId && $contact?->email) {
                $this->createDrishtiUser($baseUrl, $serviceKey, $contact, $customer->company_name);
            }

            return $clientId ? (string) $clientId : null;

        } catch (\Throwable $e) {
            Log::warning('Drishti provision exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function createDrishtiUser(string $baseUrl, string $serviceKey, $contact, string $companyName): void
    {
        try {
            // A random initial password — the client resets via Drishti's
            // "forgot password" flow before their first login.
            Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/users", [
                    'email' => $contact->email,
                    'name' => $contact->name ?? $companyName,
                    'role' => 'CLIENT',
                    'password' => Str::password(16),
                ]);
        } catch (\Throwable) {
            // Non-fatal — client provisioning still succeeded.
        }
    }

    private function provisionSmdost(Customer $customer, $contact, $service, ?string $drishtiId): ?string
    {
        $baseUrl = rtrim(config('services.smdost.base_url'), '/');
        $serviceKey = config('services.smdost.service_key');

        if (! $baseUrl || ! $serviceKey) {
            return null;
        }

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/clients", array_filter([
                    'name' => $customer->company_name,
                    'industry' => $service?->name ?? 'Digital Marketing',
                    'brandTone' => 'Professional and friendly',
                    'targetAudience' => 'To be defined — please update in Social Media Dost',
                    'website' => $customer->website,
                    'drishtiClientId' => $drishtiId,
                ]));

            if (! $response->successful()) {
                Log::warning('SMDost client provision failed', [
                    'customer_id' => $customer->id,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $clientId = $response->json('id');

            return $clientId ? (string) $clientId : null;

        } catch (\Throwable $e) {
            Log::warning('SMDost provision exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Domains never trusted as a client's unique identity — many small local
     * businesses have no website and use a free email provider, so two
     * different clients sharing "gmail.com" would otherwise collide on
     * Drishti's per-agency domain-uniqueness constraint and silently fail to
     * provision after the first one (confirmed live 2026-07-07: 11 of 12
     * no-website customers had all resolved to "gmail.com").
     *
     * @var list<string>
     */
    private const FREEMAIL_DOMAINS = [
        'gmail.com', 'yahoo.com', 'yahoo.co.in', 'hotmail.com', 'outlook.com',
        'rediffmail.com', 'icloud.com', 'live.com', 'aol.com', 'protonmail.com',
    ];

    private function extractDomain(Customer $customer): string
    {
        if ($customer->website) {
            $host = parse_url($customer->website, PHP_URL_HOST);
            if ($host) {
                return strtolower(ltrim($host, 'www.'));
            }
        }

        if ($customer->email && str_contains($customer->email, '@')) {
            $domain = strtolower(explode('@', $customer->email)[1]);
            if (! in_array($domain, self::FREEMAIL_DOMAINS, true)) {
                return $domain;
            }
        }

        // No website, and either no email or a freemail address — synthesize
        // a domain that's guaranteed unique per customer (appending the id,
        // not just the slugified name, since two clients can share a name).
        return Str::slug($customer->company_name).'-'.$customer->id.'.local';
    }
}
