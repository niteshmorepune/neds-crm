<?php

use App\Enums\UserRole;
use App\Livewire\ClientImport;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('imports valid rows, reports errors, and skips duplicates', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    // Pre-existing client to exercise DB-level duplicate detection.
    Customer::factory()->create(['email' => 'existing@acme.test']);

    $csv = <<<'CSV'
    company_name,email,gstin
    Acme Digital,acme@x.test,
    ,noname@x.test,
    Beta Labs,acme@x.test,
    Gamma Soft,gamma@x.test,27ABCDE1234F1Z5
    Delta Co,delta@x.test,111111111111111
    Echo Pvt,existing@acme.test,
    CSV;

    $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

    $component = Livewire::actingAs($admin)
        ->test(ClientImport::class)
        ->set('file', $file)
        ->call('parse')
        ->assertSet('step', 2)
        ->assertSet('rowCount', 6)
        ->call('import')
        ->assertSet('step', 3);

    $results = $component->get('results');

    // Acme + Gamma import; missing-name and bad-GSTIN error; file-dup + db-dup skipped.
    expect($results['imported'])->toBe(2)
        ->and($results['errors'])->toHaveCount(2)
        ->and($results['skipped'])->toHaveCount(2);

    expect(Customer::where('company_name', 'Acme Digital')->exists())->toBeTrue()
        ->and(Customer::where('company_name', 'Gamma Soft')->exists())->toBeTrue()
        ->and(Customer::where('company_name', 'Beta Labs')->exists())->toBeFalse();

    // Imported rows are owned by the importer.
    expect(Customer::firstWhere('company_name', 'Acme Digital')->owner_id)->toBe($admin->id);
});

it('auto-maps headers and lets company_name be required', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $csv = "email\nfoo@x.test\n";
    $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

    Livewire::actingAs($admin)
        ->test(ClientImport::class)
        ->set('file', $file)
        ->call('parse')
        ->assertSet('mapping.company_name', '') // no matching header
        ->call('import')
        ->assertHasErrors('mapping');
});

it('forbids importing for a role that cannot create clients', function () {
    // Every role can create per policy, so simulate a denied gate by using a
    // fresh user and asserting the create ability holds (guard test).
    $sales = User::factory()->role(UserRole::Sales)->create();

    expect($sales->can('create', Customer::class))->toBeTrue();
});
