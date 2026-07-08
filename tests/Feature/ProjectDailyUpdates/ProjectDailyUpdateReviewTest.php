<?php

use App\Enums\UserRole;
use App\Livewire\ProjectDailyUpdateReview;
use App\Mail\ProjectDailyUpdate as ProjectDailyUpdateMail;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function pendingDraftFor(Project $project): Note
{
    return $project->notes()->create([
        'user_id' => null,
        'body' => 'We completed the homepage redesign for you today.',
        'visible_to_client' => false,
        'ai_generated' => true,
    ]);
}

it('shows pending drafts to the project owner', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    pendingDraftFor($project);

    Livewire::actingAs($owner)
        ->test(ProjectDailyUpdateReview::class, ['project' => $project])
        ->assertSee('Pending Client Update')
        ->assertSee('homepage redesign');
});

it('lets the owner approve a draft, publishing it to the client and emailing the billing contact', function () {
    Mail::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['email' => 'client@example.com']);
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    $note = pendingDraftFor($project);

    Livewire::actingAs($owner)
        ->test(ProjectDailyUpdateReview::class, ['project' => $project])
        ->call('approve', $note->id);

    $note->refresh();
    expect($note->visible_to_client)->toBeTrue();

    Mail::assertQueued(ProjectDailyUpdateMail::class, fn ($mail) => $mail->hasTo('client@example.com') && $mail->note->id === $note->id);
});

it('lets an admin or manager approve even when they are not the owner', function () {
    Mail::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    $note = pendingDraftFor($project);

    Livewire::actingAs($manager)
        ->test(ProjectDailyUpdateReview::class, ['project' => $project])
        ->call('approve', $note->id);

    expect($note->refresh()->visible_to_client)->toBeTrue();
});

it('lets the owner edit the draft body before approving', function () {
    Mail::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    $note = pendingDraftFor($project);

    Livewire::actingAs($owner)
        ->test(ProjectDailyUpdateReview::class, ['project' => $project])
        ->set("editedBody.{$note->id}", 'Edited: everything is on track.')
        ->call('approve', $note->id);

    expect($note->refresh()->body)->toBe('Edited: everything is on track.');
});

it('lets the owner discard a draft without publishing or emailing it', function () {
    Mail::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    $note = pendingDraftFor($project);

    Livewire::actingAs($owner)
        ->test(ProjectDailyUpdateReview::class, ['project' => $project])
        ->call('discard', $note->id);

    expect(Note::find($note->id))->toBeNull();
    Mail::assertNothingQueued();
});

it('blocks a non-owner, non-admin/manager staff member from approving or discarding', function () {
    Mail::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $outsider = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    $note = pendingDraftFor($project);

    Livewire::actingAs($outsider)
        ->test(ProjectDailyUpdateReview::class, ['project' => $project])
        ->call('approve', $note->id)
        ->assertForbidden();

    expect($note->refresh()->visible_to_client)->toBeFalse();
});
