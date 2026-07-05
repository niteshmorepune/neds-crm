<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Jobs\DraftFestivalGreetingContent;
use App\Models\ContentPiece;
use App\Models\Festival;
use App\Models\Project;
use Illuminate\Console\Command;

class DraftFestivalGreetings extends Command
{
    /** Days of lead time before a festival to draft greeting content. */
    private const LEAD_DAYS = 7;

    private const SERVICE_SLUGS = ['social-media', 'gmb'];

    protected $signature = 'app:draft-festival-greetings';

    protected $description = 'Queue AI-drafted client greeting content for festivals coming up within the lead window.';

    public function handle(): int
    {
        $festivals = Festival::active()->upcomingWithin(self::LEAD_DAYS)->get();

        if ($festivals->isEmpty()) {
            $this->info('No festivals within the lead window.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($festivals as $festival) {
            $projects = Project::query()
                ->where('status', ProjectStatus::Active)
                ->whereHas('service', fn ($q) => $q->whereIn('slug', self::SERVICE_SLUGS))
                ->get();

            foreach ($projects as $project) {
                $alreadyDrafted = ContentPiece::where('project_id', $project->id)
                    ->where('festival_id', $festival->id)
                    ->exists();

                if ($alreadyDrafted) {
                    $skipped++;

                    continue;
                }

                DraftFestivalGreetingContent::dispatch($festival->id, $project->id);
                $dispatched++;
            }
        }

        $this->info("Done — {$dispatched} dispatched, {$skipped} already drafted.");

        return self::SUCCESS;
    }
}
