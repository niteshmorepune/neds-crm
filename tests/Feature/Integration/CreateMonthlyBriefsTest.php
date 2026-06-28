<?php

use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Service;
use Database\Seeders\MenuItemsSeeder;
use Database\Seeders\ServicesSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed([ServicesSeeder::class, MenuItemsSeeder::class]);

    config([
        'services.smdost.base_url'    => 'https://smdost.test',
        'services.smdost.service_key' => 'test-smdost-key',
    ]);
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function briefResponse(string $id = 'brief-001'): array
{
    return ['id' => $id, 'title' => 'Test Brief'];
}

function socialMediaProject(Customer $customer): Project
{
    $service = Service::where('slug', 'social-media')->first();

    return Project::factory()->create([
        'customer_id' => $customer->id,
        'service_id'  => $service->id,
        'status'      => ProjectStatus::Active,
        'name'        => 'Acme Social Media',
    ]);
}

function gmbProject(Customer $customer): Project
{
    $service = Service::where('slug', 'gmb')->first();

    return Project::factory()->create([
        'customer_id' => $customer->id,
        'service_id'  => $service->id,
        'status'      => ProjectStatus::Active,
        'name'        => 'Acme GMB',
    ]);
}

// ─── Happy path ───────────────────────────────────────────────────────────────

it('creates a brief for an active social media project', function () {
    Http::fake(['https://smdost.test/api/briefs' => Http::response(briefResponse('brief-sm'), 201)]);

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-123']);
    socialMediaProject($customer);

    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($req) =>
        $req->url() === 'https://smdost.test/api/briefs'
        && $req->header('X-Service-Key')[0] === 'test-smdost-key'
        && $req->data()['clientId'] === 'smdost-123'
        && count($req->data()['platforms']) === 2
        && $req->data()['platforms'][0]['platform'] === 'Instagram'
        && $req->data()['platforms'][1]['platform'] === 'Facebook'
    );

    $activity = Activity::where('subject_type', Customer::class)
        ->where('subject_id', $customer->id)
        ->where('event', 'smdost_brief_created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->changes['brief_id'])->toBe('brief-sm')
        ->and($activity->changes['service'])->toBe('Social Media');
});

it('creates a brief for an active GMB project with Google Business platform', function () {
    Http::fake(['https://smdost.test/api/briefs' => Http::response(briefResponse('brief-gmb'), 201)]);

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-gmb']);
    gmbProject($customer);

    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertSent(fn ($req) =>
        count($req->data()['platforms']) === 1
        && $req->data()['platforms'][0]['platform'] === 'Google Business'
    );
});

it('includes the project name and month in the brief title', function () {
    Http::fake(['https://smdost.test/api/briefs' => Http::response(briefResponse(), 201)]);

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-title']);
    socialMediaProject($customer);

    $month = now()->format('F Y'); // e.g. "June 2026"
    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertSent(fn ($req) => str_contains($req->data()['title'], $month));
});

// ─── Skipping ─────────────────────────────────────────────────────────────────

it('skips projects where the customer has no smdost_client_id', function () {
    Http::fake();

    $customer = Customer::factory()->create(['smdost_client_id' => null]);
    socialMediaProject($customer);

    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertNothingSent();
    expect(Activity::where('event', 'smdost_brief_created')->count())->toBe(0);
});

it('skips projects with non-social-media services (SEO, Website Dev, etc.)', function () {
    Http::fake();

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-seo']);
    $service  = Service::where('slug', 'seo')->first();
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id, 'status' => ProjectStatus::Active]);

    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertNothingSent();
});

it('skips completed projects', function () {
    Http::fake();

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-done']);
    $service  = Service::where('slug', 'social-media')->first();
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id, 'status' => ProjectStatus::Completed]);

    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertNothingSent();
});

it('skips on-hold projects', function () {
    Http::fake();

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-hold']);
    $service  = Service::where('slug', 'social-media')->first();
    Project::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id, 'status' => ProjectStatus::OnHold]);

    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertNothingSent();
});

// ─── Idempotency ──────────────────────────────────────────────────────────────

it('is idempotent — does not create a second brief if one already exists for the month', function () {
    Http::fake(['https://smdost.test/api/briefs' => Http::response(briefResponse(), 201)]);

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-idem']);
    socialMediaProject($customer);

    // First run — should create the brief
    $this->artisan('app:create-monthly-briefs')->assertSuccessful();
    // Second run same month — should skip
    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    Http::assertSentCount(1);
    expect(Activity::where('event', 'smdost_brief_created')->count())->toBe(1);
});

// ─── Resilience ───────────────────────────────────────────────────────────────

it('continues processing other projects when one smdost call fails', function () {
    $customer1 = Customer::factory()->create(['smdost_client_id' => 'smdost-ok']);
    $customer2 = Customer::factory()->create(['smdost_client_id' => 'smdost-fail']);

    socialMediaProject($customer1);
    socialMediaProject($customer2);

    Http::fake([
        'https://smdost.test/api/briefs' => Http::sequence()
            ->push(briefResponse('brief-ok'), 201)
            ->push(['error' => 'Server error'], 500),
    ]);

    $this->artisan('app:create-monthly-briefs')->assertSuccessful();

    // One succeeded, one failed — only one activity
    expect(Activity::where('event', 'smdost_brief_created')->count())->toBe(1);
});

// ─── --month option ───────────────────────────────────────────────────────────

it('accepts --month option to create briefs for a specific month', function () {
    Http::fake(['https://smdost.test/api/briefs' => Http::response(briefResponse(), 201)]);

    $customer = Customer::factory()->create(['smdost_client_id' => 'smdost-opt']);
    socialMediaProject($customer);

    $this->artisan('app:create-monthly-briefs', ['--month' => '2026-08'])->assertSuccessful();

    Http::assertSent(fn ($req) =>
        str_contains($req->data()['title'], 'August 2026')
        && str_contains($req->data()['scheduledMonth'], '2026-08')
    );

    $activity = Activity::where('event', 'smdost_brief_created')->first();
    expect($activity->changes['month'])->toBe('2026-08');
});
