<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\SyncContactNameToWadeskJob;
use App\Jobs\SyncLeadToWadeskJob;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config([
        'services.whatsapp_webhook.token' => 'wa-webhook-secret',
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key_lead_sync' => 'wadesk-secret',
    ]);
});

function postContactName(array $overrides = []): TestResponse
{
    return test()->postJson('/api/webhooks/wadesk/contact-name', array_merge([
        'phone' => '919876543210',
        'name' => 'Ashvini Kulkarni',
    ], $overrides), ['Authorization' => 'Bearer wa-webhook-secret']);
}

// --- wadesk.in -> CRM -------------------------------------------------------

it('rejects a request without the wadesk.in bearer token', function () {
    test()->postJson('/api/webhooks/wadesk/contact-name', ['phone' => '919876543210', 'name' => 'X'])
        ->assertUnauthorized();
});

it('renames the matching open Lead and Client contact from a wadesk.in rename', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['name' => 'Ashavini Sonawne', 'phone' => '+91 98765 43210']);
    $contact = Contact::factory()->for(Customer::factory())->create(['name' => 'Old Name', 'phone' => '98765-43210']);

    postContactName()
        ->assertOk()
        ->assertJson(['status' => 'ok', 'leads_updated' => 1, 'contacts_updated' => 1]);

    expect($lead->fresh()->name)->toBe('Ashvini Kulkarni')
        ->and($contact->fresh()->name)->toBe('Ashvini Kulkarni');
});

it('matches a Lead by its alternate phone', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['name' => 'Old', 'phone' => '911111111111', 'alternate_phone' => '9876543210']);

    postContactName()->assertOk()->assertJson(['leads_updated' => 1]);

    expect($lead->fresh()->name)->toBe('Ashvini Kulkarni');
});

it('does not rename a closed Lead', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['name' => 'Old', 'phone' => '919876543210', 'status' => LeadStatus::Lost]);

    postContactName()->assertOk()->assertJson(['leads_updated' => 0]);

    expect($lead->fresh()->name)->toBe('Old');
});

it('never changes a Client company name', function () {
    Queue::fake();
    $customer = Customer::factory()->create(['company_name' => 'Exim Internationals', 'phone' => '919876543210']);

    postContactName()->assertOk();

    expect($customer->fresh()->company_name)->toBe('Exim Internationals');
});

it('does not echo a wadesk.in rename back to wadesk.in', function () {
    Queue::fake();
    Lead::factory()->create(['name' => 'Old', 'phone' => '919876543210']);
    Contact::factory()->for(Customer::factory())->create(['name' => 'Old', 'phone' => '919876543210']);

    postContactName()->assertOk();

    Queue::assertNotPushed(SyncContactNameToWadeskJob::class);
});

it('records the wadesk.in rename in the activity log', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['name' => 'Old', 'phone' => '919876543210']);

    postContactName()->assertOk();

    expect($lead->activities()->where('event', 'updated')->latest('id')->first()?->changes)
        ->toHaveKey('name');
});

it('ignores a phone number too short to match safely', function () {
    Queue::fake();
    $lead = Lead::factory()->create(['name' => 'Old', 'phone' => '12345']);

    postContactName(['phone' => '12345'])->assertOk()->assertJson(['status' => 'ignored']);

    expect($lead->fresh()->name)->toBe('Old');
});

// --- CRM -> wadesk.in -------------------------------------------------------

it('pushes a Lead rename to wadesk.in with both phones', function () {
    $lead = Lead::factory()->create(['name' => 'Old', 'phone' => '919876543210', 'alternate_phone' => '9123456789']);
    Queue::fake();

    $lead->update(['name' => 'New Name']);

    Queue::assertPushed(SyncContactNameToWadeskJob::class, fn ($job) => $job->name === 'New Name'
        && $job->phones === ['919876543210', '9123456789']);
});

it('pushes a Client contact rename to wadesk.in', function () {
    $contact = Contact::factory()->for(Customer::factory())->create(['name' => 'Old', 'phone' => '9876543210']);
    Queue::fake();

    $contact->update(['name' => 'New Name']);

    Queue::assertPushed(SyncContactNameToWadeskJob::class, fn ($job) => $job->name === 'New Name'
        && $job->phones === ['9876543210']);
});

it('does not push when a Lead is created (only a rename counts)', function () {
    Queue::fake();

    // A Sales user makes created()'s autoAssign() do its nested save — the
    // real path where every attribute still reads as "changed".
    User::factory()->create(['role' => UserRole::Sales]);
    $lead = Lead::factory()->create(['name' => 'Brand New', 'phone' => '919876543210', 'owner_id' => null]);

    expect($lead->fresh()->owner_id)->not->toBeNull();
    Queue::assertNotPushed(SyncContactNameToWadeskJob::class);
});

it('does not push when the name did not change', function () {
    $lead = Lead::factory()->create(['name' => 'Same', 'phone' => '919876543210']);
    Queue::fake();

    $lead->update(['company' => 'Something Else']);

    Queue::assertNotPushed(SyncContactNameToWadeskJob::class);
});

it('never pushes the WhatsApp placeholder name', function () {
    $lead = Lead::factory()->create(['name' => 'Real Name', 'phone' => '919876543210']);
    Queue::fake();

    $lead->update(['name' => Lead::PLACEHOLDER_NAME]);

    Queue::assertNotPushed(SyncContactNameToWadeskJob::class);
});

it('does not push when the record has no phone', function () {
    $contact = Contact::factory()->for(Customer::factory())->create(['name' => 'Old', 'phone' => null]);
    Queue::fake();

    $contact->update(['name' => 'New Name']);

    Queue::assertNotPushed(SyncContactNameToWadeskJob::class);
});

it('posts digits-only phones and the name to wadesk.in with the lead-sync service key', function () {
    Http::fake(['https://wadesk.test/api/contacts/sync-name' => Http::response(['updated' => 1], 200)]);

    (new SyncContactNameToWadeskJob(['+91 98765 43210', '123'], 'New Name'))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://wadesk.test/api/contacts/sync-name'
        && $request['phones'] === ['919876543210']
        && $request['name'] === 'New Name'
        && $request->hasHeader('X-Service-Key', 'wadesk-secret'));
});

it('logs a warning but does not throw when wadesk.in fails', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    Log::spy();

    (new SyncContactNameToWadeskJob(['919876543210'], 'New Name'))->handle();

    Log::shouldHaveReceived('warning')->once();
});

it('stages a placeholder-named Lead in wadesk.in without sending the placeholder as its name', function () {
    config(['services.wadesk.marketing_number' => '918888888888']);
    Http::fake(['https://wadesk.test/api/leads/sync' => Http::response(['conversationId' => 'c1'], 200)]);
    $lead = Lead::factory()->create(['name' => Lead::PLACEHOLDER_NAME, 'phone' => '919876543210']);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://wadesk.test/api/leads/sync'
        && $request['name'] === null);
});

it('stages a Lead stored without a country code under its real 91-prefixed wadesk.in number', function () {
    config(['services.wadesk.marketing_number' => '918888888888']);
    Http::fake(['https://wadesk.test/api/leads/sync' => Http::response(['conversationId' => 'c1'], 200)]);
    $lead = Lead::factory()->create(['name' => 'Ten Digit', 'phone' => '8529857994']);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    // Real bug (lead #326): sending the bare 10 digits made wadesk.in create
    // an empty duplicate contact/chat instead of matching the real one.
    Http::assertSent(fn ($request) => $request->url() === 'https://wadesk.test/api/leads/sync'
        && $request['phone'] === '918529857994');
});
