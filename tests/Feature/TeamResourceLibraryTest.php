<?php

use App\Enums\TeamResourceCategory;
use App\Enums\UserRole;
use App\Livewire\TeamResourceLibrary;
use App\Models\TeamResource;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('lets an admin upload a file with a category and role restriction', function () {
    $upload = UploadedFile::fake()->create('plugin.zip', 500);

    Livewire::actingAs($this->admin)
        ->test(TeamResourceLibrary::class)
        ->call('newResource')
        ->set('title', 'Yoast SEO v22')
        ->set('category', TeamResourceCategory::PluginsSoftware->value)
        ->set('file', $upload)
        ->set('visibleRoles', [UserRole::Support->value])
        ->call('save')
        ->assertHasNoErrors();

    $resource = TeamResource::firstWhere('title', 'Yoast SEO v22');
    expect($resource)->not->toBeNull()
        ->and($resource->category)->toBe(TeamResourceCategory::PluginsSoftware)
        ->and($resource->uploaded_by)->toBe($this->admin->id)
        ->and($resource->visibleRoles->pluck('role')->all())->toBe([UserRole::Support]);

    Storage::disk('local')->assertExists($resource->path);
});

it('requires a title and a file', function () {
    Livewire::actingAs($this->admin)
        ->test(TeamResourceLibrary::class)
        ->call('newResource')
        ->call('save')
        ->assertHasErrors(['title', 'file']);
});

it('forbids a non-admin/manager from uploading a file', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    Livewire::actingAs($support)
        ->test(TeamResourceLibrary::class)
        ->call('newResource')
        ->assertForbidden();
});

it('hides a role-restricted resource from a user whose role does not match', function () {
    $resource = TeamResource::factory()->create(['title' => 'GST Certificate']);
    $resource->syncVisibleRoles([UserRole::Accounts]);

    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(TeamResourceLibrary::class)
        ->assertDontSee('GST Certificate');
});

it('shows a role-restricted resource to a matching role and to admin regardless', function () {
    $resource = TeamResource::factory()->create(['title' => 'GST Certificate']);
    $resource->syncVisibleRoles([UserRole::Accounts]);

    $accounts = User::factory()->role(UserRole::Accounts)->create();

    Livewire::actingAs($accounts)
        ->test(TeamResourceLibrary::class)
        ->assertSee('GST Certificate');

    Livewire::actingAs($this->admin)
        ->test(TeamResourceLibrary::class)
        ->assertSee('GST Certificate');
});

it('lets an admin edit a resource\'s metadata without replacing the file', function () {
    $resource = TeamResource::factory()->create(['title' => 'Old Title', 'path' => 'team-resources/keep-me.pdf']);

    Livewire::actingAs($this->admin)
        ->test(TeamResourceLibrary::class)
        ->call('edit', $resource->id)
        ->set('title', 'New Title')
        ->call('save')
        ->assertHasNoErrors();

    expect($resource->fresh()->title)->toBe('New Title')
        ->and($resource->fresh()->path)->toBe('team-resources/keep-me.pdf');
});

it('deletes a resource and its underlying file', function () {
    $upload = UploadedFile::fake()->create('installer.zip', 200);
    $path = $upload->store('team-resources', 'local');
    $resource = TeamResource::factory()->create(['path' => $path]);

    Livewire::actingAs($this->admin)
        ->test(TeamResourceLibrary::class)
        ->call('delete', $resource->id);

    expect(TeamResource::find($resource->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('blocks downloading a resource restricted to a different role', function () {
    $resource = TeamResource::factory()->create();
    $resource->syncVisibleRoles([UserRole::Accounts]);

    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)
        ->get(route('team-resources.download', $resource))
        ->assertForbidden();
});

it('renders the combined Resources page for every role', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get(route('resources.index'))
        ->assertOk();
})->with([
    'admin' => UserRole::Admin,
    'manager' => UserRole::Manager,
    'sales' => UserRole::Sales,
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
    'intern' => UserRole::Intern,
    'telecaller' => UserRole::Telecaller,
]);
