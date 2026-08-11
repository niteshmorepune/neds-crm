<?php

use App\Enums\DeliverableStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::Sales]);
    $this->customer = Customer::factory()->create(['owner_id' => $this->owner->id]);
    $this->contact = Contact::factory()->portalUser()->create(['customer_id' => $this->customer->id]);
    $this->project = Project::factory()->create(['customer_id' => $this->customer->id, 'owner_id' => $this->owner->id]);
});

it('shows a deliverable marked Received in the timeline', function () {
    $deliverable = $this->project->deliverables()->create(['title' => 'GST Certificate', 'status' => DeliverableStatus::Pending]);
    $deliverable->update(['status' => DeliverableStatus::Received]);

    $this->actingAs($this->contact, 'portal')
        ->get(route('portal.projects.show', $this->project))
        ->assertOk()
        ->assertSee('GST Certificate — marked Received');
});

it('does not show a deliverable marked Submitted (client-initiated) in the timeline', function () {
    $deliverable = $this->project->deliverables()->create(['title' => 'Logo Files', 'status' => DeliverableStatus::Pending]);
    $deliverable->update(['status' => DeliverableStatus::Submitted]);

    $this->actingAs($this->contact, 'portal')
        ->get(route('portal.projects.show', $this->project))
        ->assertOk()
        ->assertDontSee('Logo Files — marked Received')
        ->assertSee('No milestones yet');
});

it('shows a project status change in the timeline', function () {
    $this->project->update(['status' => ProjectStatus::Completed]);

    $this->actingAs($this->contact, 'portal')
        ->get(route('portal.projects.show', $this->project))
        ->assertOk()
        ->assertSee('Project status changed to Completed');
});

it('does not show a non-status project update in the timeline', function () {
    $this->project->update(['description' => 'Updated scope notes for internal reference.']);

    $this->actingAs($this->contact, 'portal')
        ->get(route('portal.projects.show', $this->project))
        ->assertOk()
        ->assertSee('No milestones yet');
});

it('sorts mixed deliverable and project events reverse-chronologically', function () {
    $deliverable = $this->project->deliverables()->create(['title' => 'Brand Assets', 'status' => DeliverableStatus::Pending]);

    $this->travelTo(now()->subDays(2));
    $deliverable->update(['status' => DeliverableStatus::Received]);

    $this->travelTo(now()->addDays(2));
    $this->project->update(['status' => ProjectStatus::OnHold]);
    $this->travelBack();

    $response = $this->actingAs($this->contact, 'portal')
        ->get(route('portal.projects.show', $this->project))
        ->assertOk();

    $content = $response->getContent();
    $onHoldPos = strpos($content, 'Project status changed to On Hold');
    $receivedPos = strpos($content, 'Brand Assets — marked Received');

    expect($onHoldPos)->not->toBeFalse()
        ->and($receivedPos)->not->toBeFalse()
        ->and($onHoldPos)->toBeLessThan($receivedPos);
});

it('shows a friendly empty state when nothing timeline-worthy has happened yet', function () {
    $this->actingAs($this->contact, 'portal')
        ->get(route('portal.projects.show', $this->project))
        ->assertOk()
        ->assertSee('No milestones yet — check back as work progresses.');
});
