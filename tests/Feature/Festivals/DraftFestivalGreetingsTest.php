<?php

use App\Enums\ProjectStatus;
use App\Jobs\DraftFestivalGreetingContent;
use App\Models\ContentPiece;
use App\Models\Festival;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Support\Facades\Bus;

it('dispatches a draft job only for active social/gmb projects with a festival in the lead window', function () {
    Bus::fake();

    $festival = Festival::factory()->create(['date' => now()->addDays(5)->toDateString(), 'is_active' => true]);
    $tooFar = Festival::factory()->create(['date' => now()->addDays(20)->toDateString(), 'is_active' => true]);

    $socialService = Service::factory()->create(['slug' => 'social-media']);
    $gmbService = Service::factory()->create(['slug' => 'gmb']);
    $webService = Service::factory()->create(['slug' => 'website-development']);

    $socialProject = Project::factory()->create(['service_id' => $socialService->id, 'status' => ProjectStatus::Active]);
    $gmbProject = Project::factory()->create(['service_id' => $gmbService->id, 'status' => ProjectStatus::Active]);
    $webProject = Project::factory()->create(['service_id' => $webService->id, 'status' => ProjectStatus::Active]);
    $onHoldSocialProject = Project::factory()->create(['service_id' => $socialService->id, 'status' => ProjectStatus::OnHold]);

    $this->artisan('app:draft-festival-greetings')->assertSuccessful();

    Bus::assertDispatched(DraftFestivalGreetingContent::class, fn ($job) => $job->festivalId === $festival->id && $job->projectId === $socialProject->id);
    Bus::assertDispatched(DraftFestivalGreetingContent::class, fn ($job) => $job->festivalId === $festival->id && $job->projectId === $gmbProject->id);
    Bus::assertNotDispatched(DraftFestivalGreetingContent::class, fn ($job) => $job->projectId === $webProject->id);
    Bus::assertNotDispatched(DraftFestivalGreetingContent::class, fn ($job) => $job->projectId === $onHoldSocialProject->id);
    Bus::assertNotDispatched(DraftFestivalGreetingContent::class, fn ($job) => $job->festivalId === $tooFar->id);
});

it('skips a project/festival pair that already has a draft', function () {
    Bus::fake();

    $festival = Festival::factory()->create(['date' => now()->addDays(3)->toDateString()]);
    $service = Service::factory()->create(['slug' => 'social-media']);
    $project = Project::factory()->create(['service_id' => $service->id, 'status' => ProjectStatus::Active]);

    ContentPiece::factory()->create([
        'project_id' => $project->id,
        'festival_id' => $festival->id,
    ]);

    $this->artisan('app:draft-festival-greetings')->assertSuccessful();

    Bus::assertNotDispatched(DraftFestivalGreetingContent::class);
});
