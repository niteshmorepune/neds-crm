<?php

use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Jobs\ImportMetaLead;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['services.meta.page_access_token' => 'test-page-token', 'services.meta.graph_api_version' => 'v19.0']);
});

function fakeMetaGraphResponse(array $fieldData, array $overrides = []): void
{
    Http::fake([
        'graph.facebook.com/*' => Http::response(array_merge([
            'id' => 'lg-1',
            'ad_id' => 'ad-42',
            'form_id' => 'form-7',
            'created_time' => now()->toIso8601String(),
            'field_data' => $fieldData,
        ], $overrides)),
    ]);
}

it('creates a lead from the fetched field data', function () {
    fakeMetaGraphResponse([
        ['name' => 'full_name', 'values' => ['Priya Shah']],
        ['name' => 'email', 'values' => ['priya@shah.test']],
        ['name' => 'phone_number', 'values' => ['9876543210']],
        ['name' => 'company_name', 'values' => ['Shah Traders']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->name)->toBe('Priya Shah')
        ->and($lead->email)->toBe('priya@shah.test')
        ->and($lead->phone)->toBe('9876543210')
        ->and($lead->company)->toBe('Shah Traders')
        ->and($lead->source)->toBe(LeadSource::MetaAds)
        ->and($lead->utm_source)->toBe('meta')
        ->and($lead->utm_medium)->toBe('paid_social')
        ->and($lead->utm_campaign)->toBe('ad-42');
});

it('builds the name from first_name + last_name when full_name is absent', function () {
    fakeMetaGraphResponse([
        ['name' => 'first_name', 'values' => ['Ravi']],
        ['name' => 'last_name', 'values' => ['Kumar']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->name)->toBe('Ravi Kumar');
});

it('falls back to a generic name when nothing usable is present', function () {
    fakeMetaGraphResponse([]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->name)->toBe('Facebook Lead');
});

it('falls back to form_id for utm_campaign when ad_id is absent', function () {
    fakeMetaGraphResponse([], ['ad_id' => null]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->utm_campaign)->toBe('form-7');
});

it('uses the ad name for utm_campaign when the Graph API returns one', function () {
    Http::fake([
        'graph.facebook.com/*/lg-1*' => Http::response([
            'id' => 'lg-1', 'ad_id' => 'ad-42', 'form_id' => 'form-7',
            'field_data' => [['name' => 'full_name', 'values' => ['Priya Shah']]],
        ]),
        'graph.facebook.com/*/ad-42*' => Http::response(['name' => 'SEO - Pune - July V2']),
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->utm_campaign)->toBe('SEO - Pune - July V2');
});

it('falls back to the form name when there is no ad_id', function () {
    Http::fake([
        'graph.facebook.com/*/lg-1*' => Http::response([
            'id' => 'lg-1', 'ad_id' => null, 'form_id' => 'form-7', 'field_data' => [],
        ]),
        'graph.facebook.com/*/form-7*' => Http::response(['name' => 'Organic Lead Form']),
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->utm_campaign)->toBe('Organic Lead Form');
});

it('falls back to the raw ad_id when the campaign name lookup fails', function () {
    Http::fake([
        'graph.facebook.com/*/lg-1*' => Http::response([
            'id' => 'lg-1', 'ad_id' => 'ad-42', 'form_id' => 'form-7', 'field_data' => [],
        ]),
        'graph.facebook.com/*/ad-42*' => Http::response('rate limited', 429),
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->utm_campaign)->toBe('ad-42');
});

it('falls back to the raw ad_id when the campaign name lookup throws', function () {
    Http::fake([
        'graph.facebook.com/*/lg-1*' => Http::response([
            'id' => 'lg-1', 'ad_id' => 'ad-42', 'form_id' => 'form-7', 'field_data' => [],
        ]),
        'graph.facebook.com/*/ad-42*' => fn () => throw new ConnectionException('timed out'),
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->utm_campaign)->toBe('ad-42');
});

it('preserves unmapped custom question answers as a note', function () {
    fakeMetaGraphResponse([
        ['name' => 'full_name', 'values' => ['Priya Shah']],
        ['name' => 'how_did_you_hear_about_us', 'values' => ['Instagram']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead->notes()->count())->toBe(1)
        ->and($lead->notes()->first()->body)->toContain('how_did_you_hear_about_us: Instagram');
});

it('maps a custom question answer to service_id when it matches an active service name', function () {
    $service = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);
    fakeMetaGraphResponse([
        ['name' => 'full_name', 'values' => ['Priya Shah']],
        ['name' => 'which_service_are_you_interested_in', 'values' => ['SEO']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead->service_id)->toBe($service->id)
        ->and($lead->notes()->count())->toBe(0);
});

it('matches a service name case-insensitively and ignores inactive services', function () {
    Service::factory()->create(['name' => 'AI Automation', 'is_active' => false]);
    $active = Service::factory()->create(['name' => 'Website Design & Development', 'is_active' => true]);
    fakeMetaGraphResponse([
        ['name' => 'q1', 'values' => ['ai automation']],
        ['name' => 'q2', 'values' => ['website design & development']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->service_id)->toBe($active->id);
});

it('does not set service_id when no custom answer matches a known service', function () {
    Service::factory()->create(['name' => 'SEO', 'is_active' => true]);
    fakeMetaGraphResponse([
        ['name' => 'which_service_are_you_interested_in', 'values' => ['Not sure yet']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead->service_id)->toBeNull()
        ->and($lead->notes()->first()->body)->toContain('which_service_are_you_interested_in: Not sure yet');
});

it('parses a single-number budget answer into estimated_value paise', function () {
    fakeMetaGraphResponse([
        ['name' => 'what_is_your_budget', 'values' => ['25000']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead->estimated_value)->toBe(2500000)
        ->and($lead->notes()->count())->toBe(0);
});

it('averages a range-style budget answer into estimated_value paise', function () {
    fakeMetaGraphResponse([
        ['name' => 'what_is_your_budget', 'values' => ['50000-100000']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->estimated_value)->toBe(7500000);
});

it('parses a "k" shorthand budget answer into estimated_value paise', function () {
    fakeMetaGraphResponse([
        ['name' => 'what_is_your_budget', 'values' => ['10k-25k']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->estimated_value)->toBe(1750000);
});

it('leaves a non-numeric budget answer as a note instead of guessing', function () {
    fakeMetaGraphResponse([
        ['name' => 'what_is_your_budget', 'values' => ['Not sure']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead->estimated_value)->toBeNull()
        ->and($lead->notes()->first()->body)->toContain('what_is_your_budget: Not sure');
});

it('does not mistake an unrelated numeric answer for a budget', function () {
    fakeMetaGraphResponse([
        ['name' => 'how_many_years_in_business', 'values' => ['5']],
    ]);

    ImportMetaLead::dispatchSync('lg-1');

    $lead = Lead::where('meta_leadgen_id', 'lg-1')->first();
    expect($lead->estimated_value)->toBeNull()
        ->and($lead->notes()->first()->body)->toContain('how_many_years_in_business: 5');
});

it('does not create a note when there are no unmapped fields', function () {
    fakeMetaGraphResponse([['name' => 'full_name', 'values' => ['Priya Shah']]]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->notes()->count())->toBe(0);
});

it('is idempotent — does not create a second lead for an already-imported leadgen_id', function () {
    Lead::factory()->create(['meta_leadgen_id' => 'lg-1']);
    Http::fake();

    ImportMetaLead::dispatchSync('lg-1');

    Http::assertNothingSent();
    expect(Lead::where('meta_leadgen_id', 'lg-1')->count())->toBe(1);
});

it('does nothing and logs a warning when no page access token is configured', function () {
    config(['services.meta.page_access_token' => null]);
    Http::fake();
    Log::spy();

    ImportMetaLead::dispatchSync('lg-1');

    Http::assertNothingSent();
    expect(Lead::where('meta_leadgen_id', 'lg-1')->exists())->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
});

it('does not create a lead when the Graph API call fails', function () {
    Http::fake(['graph.facebook.com/*' => Http::response('invalid token', 401)]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->exists())->toBeFalse();
});

it('does not throw when the Graph API is unreachable', function () {
    Http::fake(['graph.facebook.com/*' => fn () => throw new ConnectionException('timed out')]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->exists())->toBeFalse();
});

it('auto-assigns and can be scored like any other new lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    fakeMetaGraphResponse([['name' => 'full_name', 'values' => ['Priya Shah']]]);

    ImportMetaLead::dispatchSync('lg-1');

    expect(Lead::where('meta_leadgen_id', 'lg-1')->first()->owner_id)->toBe($sales->id);
});
