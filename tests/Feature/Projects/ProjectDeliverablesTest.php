<?php

use App\Enums\DeliverableStatus;
use App\Enums\UserRole;
use App\Livewire\ProjectDeliverables;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('lets a manager add a deliverable, defaulting to Pending', function () {
    $project = Project::factory()->create();

    Livewire::actingAs($this->manager)
        ->test(ProjectDeliverables::class, ['project' => $project, 'canManage' => true])
        ->set('title', 'Company logo')
        ->set('instructions', 'High-resolution PNG or vector file')
        ->call('addDeliverable')
        ->assertHasNoErrors();

    $deliverable = $project->deliverables()->firstOrFail();
    expect($deliverable->title)->toBe('Company logo')
        ->and($deliverable->status)->toBe(DeliverableStatus::Pending)
        ->and($deliverable->created_by)->toBe($this->manager->id);
});

it('blocks managing the deliverables checklist without manage permission', function () {
    $project = Project::factory()->create();
    $outsider = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($outsider)
        ->test(ProjectDeliverables::class, ['project' => $project, 'canManage' => false])
        ->set('title', 'Company logo')
        ->call('addDeliverable')
        ->assertForbidden();

    expect($project->deliverables()->count())->toBe(0);
});

it('lets a manager update a deliverable status', function () {
    $project = Project::factory()->create();
    $deliverable = $project->deliverables()->create(['title' => 'GST details']);

    Livewire::actingAs($this->manager)
        ->test(ProjectDeliverables::class, ['project' => $project, 'canManage' => true])
        ->call('updateStatus', $deliverable->id, 'received');

    expect($deliverable->fresh()->status)->toBe(DeliverableStatus::Received);
});

it('removes a deliverable and cleans up its stored attachment files', function () {
    Storage::fake('local');
    $project = Project::factory()->create();
    $deliverable = $project->deliverables()->create(['title' => 'Photos']);
    $attachment = $deliverable->attachments()->create([
        'disk' => 'local',
        'path' => UploadedFile::fake()->create('office.jpg', 100)->store('attachments', 'local'),
        'original_name' => 'office.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 100,
    ]);

    Livewire::actingAs($this->manager)
        ->test(ProjectDeliverables::class, ['project' => $project, 'canManage' => true])
        ->call('removeDeliverable', $deliverable->id);

    expect($project->deliverables()->count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

it('shows the Client Deliverables section on the internal project page', function () {
    $project = Project::factory()->create(['owner_id' => $this->manager->id]);
    $project->deliverables()->create(['title' => 'Company logo']);

    $this->actingAs($this->manager)->get(route('projects.show', $project))
        ->assertOk()->assertSee('Client Deliverables')->assertSee('Company logo');
});

it('lets a portal contact upload a file against a deliverable, flipping it from Pending to Submitted', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id, 'name' => 'Asha']);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $deliverable = $project->deliverables()->create(['title' => 'Company logo']);

    $this->actingAs($contact, 'portal')
        ->post(route('portal.projects.deliverables.upload', [$project->id, $deliverable->id]), [
            'attachment' => UploadedFile::fake()->create('logo.png', 200, 'image/png'),
        ])->assertRedirect();

    $deliverable->refresh();
    expect($deliverable->status)->toBe(DeliverableStatus::Submitted)
        ->and($deliverable->attachments)->toHaveCount(1);

    $attachment = $deliverable->attachments->first();
    expect($attachment->contact_id)->toBe($contact->id)
        ->and($attachment->uploaderName())->toBe('Asha');
    Storage::disk('local')->assertExists($attachment->path);
});

it('does not downgrade an already-Received deliverable back to Submitted on a fresh upload', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $deliverable = $project->deliverables()->create(['title' => 'Company logo', 'status' => DeliverableStatus::Received]);

    $this->actingAs($contact, 'portal')
        ->post(route('portal.projects.deliverables.upload', [$project->id, $deliverable->id]), [
            'attachment' => UploadedFile::fake()->create('logo-v2.png', 200, 'image/png'),
        ])->assertRedirect();

    expect($deliverable->fresh()->status)->toBe(DeliverableStatus::Received);
});

it('rejects a disallowed file type on a deliverable upload', function () {
    Storage::fake('local');
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $deliverable = $project->deliverables()->create(['title' => 'Company logo']);

    $this->actingAs($contact, 'portal')
        ->post(route('portal.projects.deliverables.upload', [$project->id, $deliverable->id]), [
            'attachment' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
        ])->assertSessionHasErrors('attachment');

    expect($deliverable->fresh()->status)->toBe(DeliverableStatus::Pending);
});

it('cannot upload against another customer\'s project deliverable', function () {
    Storage::fake('local');
    $ownCustomer = Customer::factory()->create();
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $ownCustomer->id]);

    $foreignCustomer = Customer::factory()->create();
    $foreignProject = Project::factory()->create(['customer_id' => $foreignCustomer->id]);
    $foreignDeliverable = $foreignProject->deliverables()->create(['title' => 'Company logo']);

    $this->actingAs($contact, 'portal')
        ->post(route('portal.projects.deliverables.upload', [$foreignProject->id, $foreignDeliverable->id]), [
            'attachment' => UploadedFile::fake()->create('logo.png', 200, 'image/png'),
        ])->assertNotFound();

    expect($foreignDeliverable->fresh()->attachments)->toHaveCount(0);
});

it('shows the What We Need From You checklist on the portal project page', function () {
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id]);
    $project->deliverables()->create(['title' => 'Company logo', 'instructions' => 'High-res PNG please']);

    $this->actingAs($contact, 'portal')->get(route('portal.projects.show', $project))
        ->assertOk()->assertSee('What We Need From You')->assertSee('Company logo')->assertSee('High-res PNG please');
});

it('lets an authorized internal user download a deliverable attachment but blocks an outsider', function () {
    Storage::fake('local');
    $owner = User::factory()->role(UserRole::Sales)->create();
    $outsider = User::factory()->role(UserRole::Sales)->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $deliverable = $project->deliverables()->create(['title' => 'Company logo']);
    $attachment = $deliverable->attachments()->create([
        'disk' => 'local',
        'path' => UploadedFile::fake()->create('logo.png', 100)->store('attachments', 'local'),
        'original_name' => 'logo.png',
        'mime_type' => 'image/png',
        'size' => 100,
    ]);

    $this->actingAs($owner)->get(route('attachments.download', $attachment))->assertOk();
    $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertForbidden();
});
