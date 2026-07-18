<?php

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Livewire\OnboardingTaskSuggestions;
use App\Models\Deal;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function projectWithDealNote(string $noteBody): Project
{
    Bus::fake(); // suppress the automatic CreateOnboardingTasks dispatch — irrelevant noise here
    $deal = Deal::factory()->create();
    $deal->notes()->create(['user_id' => User::factory()->create()->id, 'body' => $noteBody]);

    return Project::factory()->create(['deal_id' => $deal->id, 'status' => ProjectStatus::Active]);
}

it('lets a project manager suggest tasks, then only creates the ones actually selected', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Notification::fake();
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '[{"title": "Set up Hindi translation", "description": "Client wants Hindi.", "due_in_days": 5}, {"title": "Extra CDN setup", "description": "Client mentioned a CDN.", "due_in_days": 3}]']],
            'usage' => ['input_tokens' => 40, 'output_tokens' => 20],
        ]),
    ]);
    $manager = User::factory()->role(UserRole::Manager)->create();
    $support = User::factory()->role(UserRole::Support)->create();
    $project = projectWithDealNote('Client wants a Hindi translation and a CDN.');
    $project->assignees()->attach($support->id, ['role' => 'member']);

    $component = Livewire::actingAs($manager)
        ->test(OnboardingTaskSuggestions::class, ['project' => $project])
        ->call('suggest')
        ->assertSet('suggestions.0.title', 'Set up Hindi translation')
        ->assertSet('suggestions.1.title', 'Extra CDN setup');

    // Suggesting alone must never create anything — the core "no task
    // flood" guarantee for this feature.
    expect(Task::where('project_id', $project->id)->count())->toBe(0);

    $component->set('suggestions.1.selected', false)
        ->call('addSelected');

    $tasks = Task::where('project_id', $project->id)->get();
    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->title)->toBe('Set up Hindi translation')
        ->and($tasks->first()->assignee_id)->toBe($support->id);

    Notification::assertSentTo($support, TaskAssigned::class);
});

it('forbids a non-manager from suggesting onboarding tasks', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $project = projectWithDealNote('Some requirement.');

    Livewire::actingAs($sales)
        ->test(OnboardingTaskSuggestions::class, ['project' => $project])
        ->call('suggest')
        ->assertForbidden();
});

it('hides the suggest button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $manager = User::factory()->role(UserRole::Manager)->create();
    $project = projectWithDealNote('Some requirement.');

    Livewire::actingAs($manager)
        ->test(OnboardingTaskSuggestions::class, ['project' => $project])
        ->assertDontSee('Suggest onboarding tasks');
});

it('shows a friendly message and creates nothing when the AI has no extra suggestions', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '[]']],
            'usage' => ['input_tokens' => 20, 'output_tokens' => 5],
        ]),
    ]);
    $manager = User::factory()->role(UserRole::Manager)->create();
    $project = projectWithDealNote('Nothing unusual here.');

    Livewire::actingAs($manager)
        ->test(OnboardingTaskSuggestions::class, ['project' => $project])
        ->call('suggest')
        ->assertSee('Nothing specific to suggest');

    expect(Task::where('project_id', $project->id)->count())->toBe(0);
});
