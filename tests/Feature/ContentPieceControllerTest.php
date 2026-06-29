<?php

use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Enums\UserRole;
use App\Models\ContentPiece;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

// Render tests
it('renders the content index for a project', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    actingAs($user)
        ->get(route('projects.content.index', $project))
        ->assertOk();
});

it('renders the content piece show page', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $piece = ContentPiece::factory()->create([
        'project_id' => Project::factory()->create(['owner_id' => $user->id])->id,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->get(route('content.show', $piece))
        ->assertOk();
});

// Create
it('project owner (sales) can add a content piece', function () {
    $owner = User::factory()->create(['role' => UserRole::Sales]);
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    actingAs($owner)
        ->post(route('projects.content.store', $project), [
            'workflow_type' => 'agency_led',
            'platform' => 'instagram',
            'title' => 'Festival post',
        ])
        ->assertRedirect(route('projects.content.index', $project));

    expect($project->contentPieces()->where('title', 'Festival post')->exists())->toBeTrue();
});

it('initial status is set automatically from workflow type', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    actingAs($user)->post(route('projects.content.store', $project), [
        'workflow_type' => 'neds_led',
        'platform' => 'facebook',
        'title' => 'Blog promo',
    ]);

    $piece = $project->contentPieces()->first();
    expect($piece->status)->toBe(ContentStatus::CopyDrafting);
});

it('support without project assignment cannot add a content piece', function () {
    $support = User::factory()->create(['role' => UserRole::Support]);
    $project = Project::factory()->create();

    actingAs($support)
        ->post(route('projects.content.store', $project), [
            'workflow_type' => 'agency_led',
            'platform' => 'instagram',
            'title' => 'Sneaky post',
        ])
        ->assertForbidden();
});

// Advance status
it('manager can advance an agency_led piece from pending to received', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create(['owner_id' => $user->id])->id,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->patch(route('projects.content.advance', ['project' => $piece->project_id, 'content_piece' => $piece]), [
            'status' => ContentStatus::Received->value,
        ])
        ->assertRedirect();

    expect($piece->fresh()->status)->toBe(ContentStatus::Received);
});

it('advance rejects an invalid status transition', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create(['owner_id' => $user->id])->id,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->patch(route('projects.content.advance', ['project' => $piece->project_id, 'content_piece' => $piece]), [
            'status' => ContentStatus::Published->value, // jump straight to published — not allowed
        ])
        ->assertSessionHasErrors('status');
});

it('marking as published sets published_at', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $piece = ContentPiece::factory()->create([
        'project_id' => Project::factory()->create(['owner_id' => $user->id])->id,
        'created_by' => $user->id,
        'workflow_type' => ContentWorkflowType::AgencyLed,
        'status' => ContentStatus::Scheduled,
    ]);

    actingAs($user)
        ->patch(route('projects.content.advance', ['project' => $piece->project_id, 'content_piece' => $piece]), [
            'status' => ContentStatus::Published->value,
        ]);

    expect($piece->fresh()->published_at)->not->toBeNull();
});

// Upload link generation
it('manager can generate an upload link', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => Project::factory()->create(['owner_id' => $user->id])->id,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('projects.content.upload-link', ['project' => $piece->project_id, 'content_piece' => $piece]))
        ->assertRedirect();

    expect($piece->fresh()->upload_token)->not->toBeNull();
    expect($piece->fresh()->upload_token_expires_at)->not->toBeNull();
});

it('sales cannot generate an upload link', function () {
    $user = User::factory()->create(['role' => UserRole::Sales]);
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $piece = ContentPiece::factory()->agencyLed()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('projects.content.upload-link', ['project' => $piece->project_id, 'content_piece' => $piece]))
        ->assertForbidden();
});

// Delete
it('manager can delete a content piece', function () {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $piece = ContentPiece::factory()->create([
        'project_id' => Project::factory()->create(['owner_id' => $user->id])->id,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->delete(route('content.destroy', $piece))
        ->assertRedirect();

    expect(ContentPiece::find($piece->id))->toBeNull();
});
