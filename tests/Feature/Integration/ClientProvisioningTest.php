<?php

use App\Enums\DealStage;
use App\Jobs\ProvisionClientExternallyJob;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Service;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);

    config([
        'services.drishti.base_url' => 'https://nedsdrishti.test',
        'services.drishti.service_key' => 'drishti-secret',
        'services.smdost.base_url' => 'https://smdost.test',
        'services.smdost.service_key' => 'smdost-secret',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Dispatch behaviour
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches ProvisionClientExternallyJob when a deal moves to Won', function () {
    Queue::fake();
    $deal = Deal::factory()->stage(DealStage::Proposal)->create();

    $deal->update(['stage' => DealStage::Won]);

    Queue::assertPushed(
        ProvisionClientExternallyJob::class,
        fn ($job) => $job->customerId === $deal->customer_id,
    );
});

it('does not dispatch when a deal moves to a non-Won stage', function () {
    Queue::fake();
    $deal = Deal::factory()->stage(DealStage::New)->create();

    $deal->update(['stage' => DealStage::Negotiation]);

    Queue::assertNotPushed(ProvisionClientExternallyJob::class);
});

it('does not dispatch when an unrelated deal field changes', function () {
    Queue::fake();
    $deal = Deal::factory()->stage(DealStage::Contacted)->create();

    $deal->update(['value' => 9999900]);

    Queue::assertNotPushed(ProvisionClientExternallyJob::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Job: successful provisioning
// ──────────────────────────────────────────────────────────────────────────────

it('calls Drishti and SMDost and stores returned IDs on the customer', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'drishti-abc-123']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'drishti-user-1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 'smdost-xyz-789'], 201),
    ]);

    $customer = Customer::factory()->create(['website' => 'https://example.com']);
    Contact::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'email' => 'contact@example.com', 'name' => 'Test Contact']);

    (new ProvisionClientExternallyJob($customer->id))->handle();

    $customer->refresh();
    expect($customer->drishti_client_id)->toBe('drishti-abc-123')
        ->and($customer->smdost_client_id)->toBe('smdost-xyz-789');
});

it('sends the correct payload to Drishti', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'drishti-1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 'smd-1'], 201),
    ]);

    $service = Service::factory()->create(['name' => 'SEO']);
    $customer = Customer::factory()->create([
        'company_name' => 'Acme Pvt Ltd',
        'website' => 'https://acme.co.in',
    ]);
    Contact::factory()->create([
        'customer_id' => $customer->id,
        'is_primary' => true,
        'name' => 'Ravi Kumar',
        'email' => 'ravi@acme.co.in',
    ]);
    Deal::factory()->create([
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'stage' => DealStage::Won,
    ]);

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'nedsdrishti.test/api/clients')
            && $request->data()['name'] === 'Acme Pvt Ltd'
            && $request->data()['domain'] === 'acme.co.in'
            && $request->data()['industry'] === 'SEO'
            && $request->header('X-Service-Key')[0] === 'drishti-secret';
    });
});

it('sends the correct payload to SMDost', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'drishti-1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 'smd-1'], 201),
    ]);

    $customer = Customer::factory()->create([
        'company_name' => 'Bright Digital',
        'website' => 'https://brightdigital.in',
    ]);

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'smdost.test/api/clients')
            && $request->data()['name'] === 'Bright Digital'
            && $request->header('X-Service-Key')[0] === 'smdost-secret';
    });
});

it('forwards the drishti client id to SMDost so content can be pushed directly', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'drishti-abc']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 'smd-1'], 201),
    ]);

    $customer = Customer::factory()->create();

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'smdost.test/api/clients')
            && ($request->data()['drishtiClientId'] ?? null) === 'drishti-abc';
    });
});

it('omits drishtiClientId from SMDost payload when Drishti provisioning failed', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response('error', 503),
        'smdost.test/api/clients' => Http::response(['id' => 'smd-1'], 201),
    ]);

    $customer = Customer::factory()->create();

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'smdost.test/api/clients')
            && ! array_key_exists('drishtiClientId', $request->data());
    });
});

it('writes an activity log entry after successful provisioning', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'drishti-1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 'smd-1'], 201),
    ]);

    $customer = Customer::factory()->create();

    (new ProvisionClientExternallyJob($customer->id))->handle();

    $activity = Activity::where('subject_type', Customer::class)
        ->where('subject_id', $customer->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->changes)->toHaveKey('drishti_client_id');
});

// ──────────────────────────────────────────────────────────────────────────────
// Job: failure handling
// ──────────────────────────────────────────────────────────────────────────────

it('does not throw when Drishti returns a 5xx error', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response('error', 503),
        'smdost.test/api/clients' => Http::response(['id' => 'smd-1'], 201),
    ]);

    $customer = Customer::factory()->create();

    expect(fn () => (new ProvisionClientExternallyJob($customer->id))->handle())
        ->not->toThrow(Throwable::class);

    // SMDost should still have been provisioned.
    expect($customer->fresh()->smdost_client_id)->toBe('smd-1');
    expect($customer->fresh()->drishti_client_id)->toBeNull();
});

it('does not throw when SMDost returns a 5xx error', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'drishti-1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response('error', 503),
    ]);

    $customer = Customer::factory()->create();

    expect(fn () => (new ProvisionClientExternallyJob($customer->id))->handle())
        ->not->toThrow(Throwable::class);

    expect($customer->fresh()->drishti_client_id)->toBe('drishti-1');
    expect($customer->fresh()->smdost_client_id)->toBeNull();
});

it('does not throw when both external services are unreachable', function () {
    Http::fake([
        'nedsdrishti.test/*' => Http::response('', 503),
        'smdost.test/*' => Http::response('', 503),
    ]);

    $customer = Customer::factory()->create();

    expect(fn () => (new ProvisionClientExternallyJob($customer->id))->handle())
        ->not->toThrow(Throwable::class);

    expect($customer->fresh()->drishti_client_id)->toBeNull()
        ->and($customer->fresh()->smdost_client_id)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Job: idempotency & edge cases
// ──────────────────────────────────────────────────────────────────────────────

it('skips all HTTP calls if drishti_client_id is already set (idempotent guard)', function () {
    Http::fake();

    $customer = Customer::factory()->create(['drishti_client_id' => 'already-set']);

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertNothingSent();
});

it('exits silently when the customer no longer exists', function () {
    Http::fake();

    expect(fn () => (new ProvisionClientExternallyJob(99999))->handle())
        ->not->toThrow(Throwable::class);

    Http::assertNothingSent();
});

it('does not call external apps when service keys are not configured', function () {
    config([
        'services.drishti.service_key' => null,
        'services.smdost.service_key' => null,
    ]);
    Http::fake();

    $customer = Customer::factory()->create();

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertNothingSent();
});

it('derives the domain from website URL, stripping www', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'drishti-1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 'smd-1'], 201),
    ]);

    $customer = Customer::factory()->create(['website' => 'https://www.mybusiness.in/home']);

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'nedsdrishti.test/api/clients') &&
        $req->data()['domain'] === 'mybusiness.in'
    );
});

it('falls back to email domain when website is blank', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'd1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 's1'], 201),
    ]);

    $customer = Customer::factory()->create(['website' => null, 'email' => 'hello@startup.io']);

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'nedsdrishti.test/api/clients') &&
        $req->data()['domain'] === 'startup.io'
    );
});

it('does not use a freemail domain as the client identity, since multiple clients would collide on it', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'd1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 's1'], 201),
    ]);

    $customer = Customer::factory()->create([
        'company_name' => 'MEnterprises',
        'website' => null,
        'email' => 'someone@gmail.com',
    ]);

    (new ProvisionClientExternallyJob($customer->id))->handle();

    Http::assertSent(fn ($req) => str_contains($req->url(), 'nedsdrishti.test/api/clients') &&
        $req->data()['domain'] === 'menterprises-'.$customer->id.'.local'
    );
});

it('gives two different freemail-email clients different synthesized domains', function () {
    Http::fake([
        'nedsdrishti.test/api/clients' => Http::response(['data' => ['id' => 'd1']], 201),
        'nedsdrishti.test/api/users' => Http::response(['data' => ['id' => 'u1']], 201),
        'smdost.test/api/clients' => Http::response(['id' => 's1'], 201),
    ]);

    $a = Customer::factory()->create(['company_name' => 'Alpha Co', 'website' => null, 'email' => 'a@gmail.com']);
    $b = Customer::factory()->create(['company_name' => 'Beta Co', 'website' => null, 'email' => 'b@gmail.com']);

    (new ProvisionClientExternallyJob($a->id))->handle();
    (new ProvisionClientExternallyJob($b->id))->handle();

    $domains = collect(Http::recorded())
        ->map(fn ($pair) => $pair[0])
        ->filter(fn ($req) => str_contains($req->url(), 'nedsdrishti.test/api/clients'))
        ->map(fn ($req) => $req->data()['domain'])
        ->values()
        ->all();

    expect($domains)->toHaveCount(2)->and($domains[0])->not->toBe($domains[1]);
});
