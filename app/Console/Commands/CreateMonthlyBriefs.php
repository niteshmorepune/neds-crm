<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateMonthlyBriefs extends Command
{
    protected $signature = 'app:create-monthly-briefs
                            {--month= : Target month in Y-m format (e.g. 2026-07). Defaults to current month.}';

    protected $description = 'Create SMDost content briefs for active social media and GMB projects (run on 1st of each month).';

    /**
     * Maps CRM service slugs to the SMDost platform/contentType combinations
     * that should be included in the auto-created brief.
     *
     * @var array<string, list<array{platform: string, contentType: string, postsCount: int}>>
     */
    private const SERVICE_PLATFORMS = [
        'social-media' => [
            ['platform' => 'Instagram',  'contentType' => 'IMAGE', 'postsCount' => 4],
            ['platform' => 'Facebook',   'contentType' => 'IMAGE', 'postsCount' => 4],
        ],
        'gmb' => [
            ['platform' => 'Google Business', 'contentType' => 'IMAGE', 'postsCount' => 4],
        ],
    ];

    public function handle(): int
    {
        $monthArg  = $this->option('month');
        $monthDate = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->startOfMonth();

        $monthLabel = $monthDate->format('F Y'); // "July 2026"
        $monthKey   = $monthDate->format('Y-m'); // "2026-07"

        $baseUrl    = rtrim((string) config('services.smdost.base_url'), '/');
        $serviceKey = (string) config('services.smdost.service_key');

        if (! $baseUrl || ! $serviceKey) {
            $this->error('SMDOST_API_URL or SMDOST_SERVICE_KEY is not configured.');

            return self::FAILURE;
        }

        $this->info("Creating SMDost briefs for {$monthLabel}…");

        $slugs = array_keys(self::SERVICE_PLATFORMS);

        $projects = Project::query()
            ->where('status', ProjectStatus::Active)
            ->whereHas('service', fn ($q) => $q->whereIn('slug', $slugs))
            ->whereHas('customer', fn ($q) => $q->whereNotNull('smdost_client_id'))
            ->with(['service', 'customer'])
            ->get();

        $created = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($projects as $project) {
            $customer = $project->customer;

            // Idempotency: skip if a brief was already created for this customer+month.
            $alreadyCreated = Activity::where('subject_type', Customer::class)
                ->where('subject_id', $customer->id)
                ->where('event', 'smdost_brief_created')
                ->whereJsonContains('changes->month', $monthKey)
                ->exists();

            if ($alreadyCreated) {
                $this->line("  Skipping {$customer->name} — brief already exists for {$monthLabel}.");
                $skipped++;
                continue;
            }

            $serviceName = $project->service->name;
            $slug        = $project->service->slug;
            $platforms   = self::SERVICE_PLATFORMS[$slug] ?? [];

            try {
                $response = Http::withHeader('X-Service-Key', $serviceKey)
                    ->timeout(15)
                    ->post("{$baseUrl}/api/briefs", [
                        'clientId'            => $customer->smdost_client_id,
                        'title'               => "{$project->name} — {$monthLabel}",
                        'contentGoal'         => "Monthly {$serviceName} content for {$monthLabel}",
                        'campaignDescription' => 'Auto-generated from NEDS CRM. Review goals and add any special instructions before generating content.',
                        'scheduledMonth'      => $monthDate->toIso8601String(),
                        'platforms'           => $platforms,
                    ]);

                if (! $response->successful()) {
                    $this->warn("  Failed for {$customer->name}: HTTP {$response->status()} — {$response->body()}");
                    $failed++;
                    continue;
                }

                $briefId = $response->json('id');

                Activity::create([
                    'user_id'      => null,
                    'subject_type' => Customer::class,
                    'subject_id'   => $customer->id,
                    'event'        => 'smdost_brief_created',
                    'changes'      => [
                        'month'    => $monthKey,
                        'brief_id' => $briefId,
                        'project'  => $project->name,
                        'service'  => $serviceName,
                    ],
                ]);

                $this->info("  Created brief for {$customer->name} ({$serviceName}) — ID: {$briefId}");
                $created++;

            } catch (\Throwable $e) {
                Log::warning('CreateMonthlyBriefs: exception', [
                    'customer_id' => $customer->id,
                    'error'       => $e->getMessage(),
                ]);
                $this->warn("  Exception for {$customer->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done — {$created} created, {$skipped} skipped, {$failed} failed.");

        return self::SUCCESS;
    }
}
