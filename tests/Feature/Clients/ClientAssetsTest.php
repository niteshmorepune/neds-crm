<?php

use App\Enums\ClientAssetCategory;
use App\Enums\UserRole;
use App\Livewire\ClientAssets;
use App\Models\ClientAsset;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    Storage::fake('local');
    $this->manager = User::factory()->role(UserRole::Manager)->create();
    $this->customer = Customer::factory()->create();
});

it('uploads a new client asset with a category', function () {
    $service = Service::factory()->create();

    Livewire::actingAs($this->manager)
        ->test(ClientAssets::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('newAsset')
        ->set('title', 'Logo Pack')
        ->set('category', ClientAssetCategory::BrandAssets->value)
        ->set('serviceId', $service->id)
        ->set('file', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('client_assets', [
        'customer_id' => $this->customer->id, 'title' => 'Logo Pack',
        'category' => ClientAssetCategory::BrandAssets->value, 'version' => 1,
    ]);
});

it('replacing a file archives the old one into a version and increments version', function () {
    $asset = ClientAsset::create([
        'customer_id' => $this->customer->id, 'category' => ClientAssetCategory::Other->value, 'title' => 'GST Certificate',
        'disk' => 'local', 'path' => 'client-assets/original.pdf', 'original_name' => 'original.pdf',
        'mime_type' => 'application/pdf', 'size' => 100, 'uploaded_by' => $this->manager->id, 'version' => 1,
    ]);
    Storage::disk('local')->put('client-assets/original.pdf', 'old content');

    Livewire::actingAs($this->manager)
        ->test(ClientAssets::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('startReplace', $asset->id)
        ->set('replacementFile', UploadedFile::fake()->create('updated.pdf', 50))
        ->call('replace')
        ->assertHasNoErrors();

    $asset->refresh();
    expect($asset->version)->toBe(2)
        ->and($asset->original_name)->toBe('updated.pdf')
        ->and($asset->versions()->count())->toBe(1);

    $version = $asset->versions()->first();
    expect($version->version)->toBe(1)
        ->and($version->original_name)->toBe('original.pdf');
});

it('streams the current file and an archived version, both authorized against the parent client', function () {
    $asset = ClientAsset::create([
        'customer_id' => $this->customer->id, 'category' => ClientAssetCategory::Other->value, 'title' => 'x',
        'disk' => 'local', 'path' => 'client-assets/current.pdf', 'original_name' => 'current.pdf', 'size' => 1,
    ]);
    Storage::disk('local')->put('client-assets/current.pdf', 'current');
    $version = $asset->versions()->create([
        'version' => 1, 'disk' => 'local', 'path' => 'client-assets/old.pdf', 'original_name' => 'old.pdf', 'size' => 1,
    ]);
    Storage::disk('local')->put('client-assets/old.pdf', 'old');

    $this->actingAs($this->manager)->get(route('client-assets.download', $asset))->assertOk();
    $this->actingAs($this->manager)->get(route('client-asset-versions.download', $version))->assertOk();
});

it('deleting an asset also deletes its versions rows and physical files', function () {
    $asset = ClientAsset::create([
        'customer_id' => $this->customer->id, 'category' => ClientAssetCategory::Other->value, 'title' => 'x',
        'disk' => 'local', 'path' => 'client-assets/current.pdf', 'original_name' => 'current.pdf', 'size' => 1,
    ]);
    Storage::disk('local')->put('client-assets/current.pdf', 'current');
    $version = $asset->versions()->create([
        'version' => 1, 'disk' => 'local', 'path' => 'client-assets/old.pdf', 'original_name' => 'old.pdf', 'size' => 1,
    ]);
    Storage::disk('local')->put('client-assets/old.pdf', 'old');

    Livewire::actingAs($this->manager)
        ->test(ClientAssets::class, ['customer' => $this->customer, 'canManage' => true])
        ->call('delete', $asset->id);

    $this->assertDatabaseMissing('client_assets', ['id' => $asset->id]);
    $this->assertDatabaseMissing('client_asset_versions', ['id' => $version->id]);
    Storage::disk('local')->assertMissing('client-assets/current.pdf');
    Storage::disk('local')->assertMissing('client-assets/old.pdf');
});

it('blocks a role without manageServices from uploading', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    Livewire::actingAs($accounts)
        ->test(ClientAssets::class, ['customer' => $this->customer, 'canManage' => false])
        ->call('newAsset')
        ->assertForbidden();
});
