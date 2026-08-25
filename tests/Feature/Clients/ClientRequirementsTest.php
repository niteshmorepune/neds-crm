<?php

use App\Enums\ClientAssetCategory;
use App\Enums\DeliverableStatus;
use App\Enums\UserRole;
use App\Livewire\ClientRequirements;
use App\Models\ClientAsset;
use App\Models\ClientRequirement;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->manager = User::factory()->role(UserRole::Manager)->create();
    $this->customer = Customer::factory()->create();
    $this->service = Service::factory()->create(['name' => 'Website Design & Development']);
});

it('creates a client requirement with dates and a responsible employee', function () {
    $responsible = User::factory()->create();

    Livewire::actingAs($this->manager)
        ->test(ClientRequirements::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newRequirement')
        ->set('serviceId', $this->service->id)
        ->set('title', 'Company logo files')
        ->set('requestedDate', now()->toDateString())
        ->set('dueDate', now()->addDays(7)->toDateString())
        ->set('responsibleUserId', $responsible->id)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('client_requirements', [
        'customer_id' => $this->customer->id, 'service_id' => $this->service->id,
        'title' => 'Company logo files', 'responsible_user_id' => $responsible->id,
        'status' => DeliverableStatus::Pending->value,
    ]);
});

it('rejects a due date before the requested date', function () {
    Livewire::actingAs($this->manager)
        ->test(ClientRequirements::class, ['customer' => $this->customer, 'canManage' => true])
        ->set('serviceId', $this->service->id)
        ->set('title', 'Bad dates')
        ->set('requestedDate', now()->toDateString())
        ->set('dueDate', now()->subDay()->toDateString())
        ->call('save')
        ->assertHasErrors(['dueDate']);
});

it('transitions status via updateStatus and stamps received_date once', function () {
    $requirement = ClientRequirement::create([
        'customer_id' => $this->customer->id, 'service_id' => $this->service->id, 'title' => 'Brand guidelines',
    ]);

    Livewire::actingAs($this->manager)
        ->test(ClientRequirements::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('updateStatus', $requirement->id, DeliverableStatus::Received->value);

    $requirement->refresh();
    expect($requirement->status)->toBe(DeliverableStatus::Received)
        ->and($requirement->received_date)->not->toBeNull();
});

it('attaching a received file creates exactly one ClientAsset and links it, not a duplicate attachment', function () {
    $requirement = ClientRequirement::create([
        'customer_id' => $this->customer->id, 'service_id' => $this->service->id, 'title' => 'Signed contract',
    ]);

    Livewire::actingAs($this->manager)
        ->test(ClientRequirements::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('startUpload', $requirement->id)
        ->set('file', UploadedFile::fake()->create('contract.pdf', 100))
        ->set('fileCategory', ClientAssetCategory::BusinessDocuments->value)
        ->call('uploadFile')
        ->assertHasNoErrors();

    expect(ClientAsset::where('customer_id', $this->customer->id)->count())->toBe(1);

    $requirement->refresh();
    expect($requirement->client_asset_id)->not->toBeNull()
        ->and($requirement->clientAsset->category)->toBe(ClientAssetCategory::BusinessDocuments)
        ->and($requirement->clientAsset->original_name)->toBe('contract.pdf');
});

it('leaves the linked ClientAsset intact when the requirement itself is removed', function () {
    $asset = ClientAsset::create([
        'customer_id' => $this->customer->id, 'category' => ClientAssetCategory::Other->value, 'title' => 'x',
        'disk' => 'local', 'path' => 'x', 'original_name' => 'x.pdf', 'size' => 1,
    ]);
    $requirement = ClientRequirement::create([
        'customer_id' => $this->customer->id, 'service_id' => $this->service->id, 'title' => 'x', 'client_asset_id' => $asset->id,
    ]);

    Livewire::actingAs($this->manager)
        ->test(ClientRequirements::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('remove', $requirement->id);

    $this->assertDatabaseMissing('client_requirements', ['id' => $requirement->id]);
    $this->assertDatabaseHas('client_assets', ['id' => $asset->id]);
});

it('blocks a role without manageServices from adding a requirement', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    Livewire::actingAs($accounts)
        ->test(ClientRequirements::class, ['customer' => $this->customer, 'canManage' => false])
        ->call('newRequirement')
        ->assertForbidden();
});
