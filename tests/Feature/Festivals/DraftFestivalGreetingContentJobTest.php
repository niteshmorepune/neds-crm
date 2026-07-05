<?php

use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Enums\UserRole;
use App\Jobs\DraftFestivalGreetingContent;
use App\Models\ContentPiece;
use App\Models\Festival;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Notifications\FestivalGreetingDrafted;
use App\Services\AiAssistant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function fakeFestivalGreetingText(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 40, 'output_tokens' => 20],
        ]),
    ]);
}

function festivalAiOn(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

it('creates a NedsLed content piece from the AI draft and notifies the project lead', function () {
    festivalAiOn();
    fakeFestivalGreetingText('🎉 Wishing Acme Corp a joyful Diwali filled with light and prosperity! ✨');
    Notification::fake();

    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = User::factory()->role(UserRole::Sales)->create();
    $festival = Festival::factory()->create(['name' => 'Diwali', 'date' => now()->addDays(5)->toDateString()]);
    $service = Service::factory()->create(['slug' => 'social-media']);
    $project = Project::factory()->create(['service_id' => $service->id]);
    $project->assignees()->attach($lead->id, ['role' => 'lead']);

    (new DraftFestivalGreetingContent($festival->id, $project->id))->handle(app(AiAssistant::class));

    $piece = ContentPiece::where('project_id', $project->id)->where('festival_id', $festival->id)->first();
    expect($piece)->not->toBeNull()
        ->and($piece->workflow_type)->toBe(ContentWorkflowType::NedsLed)
        ->and($piece->status)->toBe(ContentStatus::CopyDrafting)
        ->and($piece->platform)->toBe(ContentPlatform::Instagram)
        ->and($piece->copy_text)->toContain('Diwali')
        ->and($piece->created_by)->toBe($lead->id);

    Notification::assertSentTo($lead, FestivalGreetingDrafted::class);
});

it('uses Google Business as the platform for a GMB project', function () {
    festivalAiOn();
    fakeFestivalGreetingText('🎉 Happy Diwali from our team to yours!');

    $festival = Festival::factory()->create(['date' => now()->addDays(2)->toDateString()]);
    $service = Service::factory()->create(['slug' => 'gmb']);
    $project = Project::factory()->create(['service_id' => $service->id]);
    User::factory()->role(UserRole::Admin)->create();

    (new DraftFestivalGreetingContent($festival->id, $project->id))->handle(app(AiAssistant::class));

    $piece = ContentPiece::where('project_id', $project->id)->first();
    expect($piece->platform)->toBe(ContentPlatform::GoogleBusiness);
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();

    $festival = Festival::factory()->create();
    $project = Project::factory()->create();

    (new DraftFestivalGreetingContent($festival->id, $project->id))->handle(app(AiAssistant::class));

    expect(ContentPiece::where('project_id', $project->id)->exists())->toBeFalse();
});

it('does not duplicate a draft for the same project/festival pair', function () {
    festivalAiOn();
    fakeFestivalGreetingText('Greeting text');

    $festival = Festival::factory()->create();
    $project = Project::factory()->create();
    ContentPiece::factory()->create(['project_id' => $project->id, 'festival_id' => $festival->id]);

    $countBefore = ContentPiece::count();

    (new DraftFestivalGreetingContent($festival->id, $project->id))->handle(app(AiAssistant::class));

    expect(ContentPiece::count())->toBe($countBefore);
});
