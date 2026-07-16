<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\ClientRadarService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

function backdate(Model $model, Carbon $when): void
{
    $model->forceFill(['created_at' => $when])->saveQuietly();
}

it('flags a client with no contact on record at all', function () {
    $customer = Customer::factory()->create();

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows)->toHaveCount(1);
    expect($rows->first()['customer']->is($customer))->toBeTrue();
    expect($rows->first()['flags'])->toHaveKey('no_contact');
});

it('flags a client whose last touch is older than 14 days', function () {
    $customer = Customer::factory()->create();
    backdate($customer->notes()->create(['body' => 'Old note']), now()->subDays(20));

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows->first()['flags'])->toHaveKey('no_contact');
});

it('does not flag a client with a recent touch and nothing else wrong', function () {
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Just spoke to them']);

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows)->toHaveCount(0);
});

it('flags a client with an overdue invoice', function () {
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Recent touch']);
    Invoice::factory()->for($customer)->status(InvoiceStatus::Overdue)->create(['total' => 10000]);

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows->first()['flags'])->toHaveKey('overdue_invoice');
    expect($rows->first()['flags'])->not->toHaveKey('no_contact');
});

it('flags a single-service client as a growth opportunity when other active services exist', function () {
    $seo = Service::factory()->create(['name' => 'SEO']);
    Service::factory()->create(['name' => 'Google Ads']);
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Recent touch']);
    Project::factory()->for($customer)->create(['service_id' => $seo->id]);

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows->first()['flags'])->toHaveKey('upsell_opportunity');
    expect($rows->first()['flags']['upsell_opportunity']['detail'])->toContain('SEO');
});

it('does not flag a client using two different services', function () {
    $seo = Service::factory()->create();
    $ads = Service::factory()->create();
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Recent touch']);
    Project::factory()->for($customer)->create(['service_id' => $seo->id]);
    RecurringInvoice::factory()->for($customer)->create(['service_id' => $ads->id]);

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows)->toHaveCount(0);
});

it('does not flag single-service usage when only one active service line exists in the whole agency', function () {
    $only = Service::factory()->create();
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Recent touch']);
    Project::factory()->for($customer)->create(['service_id' => $only->id]);

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows)->toHaveCount(0);
});

it('flags declining activity when recent touches are well below the prior period, without also flagging no_contact', function () {
    $customer = Customer::factory()->create();

    // Previous 30-60 day window: 4 touches.
    for ($i = 0; $i < 4; $i++) {
        backdate($customer->notes()->create(['body' => "Old note {$i}"]), now()->subDays(40 + $i));
    }
    // Recent 0-30 day window: 1 touch, within the last 14 days so no_contact does not trip.
    backdate($customer->notes()->create(['body' => 'Recent note']), now()->subDays(5));

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows->first()['flags'])->toHaveKey('declining_activity');
    expect($rows->first()['flags'])->not->toHaveKey('no_contact');
});

it('flags a client with a recent low satisfaction rating', function () {
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Recent touch']);
    $ticket = Ticket::factory()->for($customer)->create();
    $ticket->satisfactionRating()->create(['rating' => 2]);

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows->first()['flags'])->toHaveKey('low_satisfaction')
        ->and($rows->first()['flags']['low_satisfaction']['detail'])->toContain('2/5');
});

it('does not flag a client whose ratings are all above the threshold', function () {
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Recent touch']);
    $ticket = Ticket::factory()->for($customer)->create();
    $ticket->satisfactionRating()->create(['rating' => 4]);

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows)->toHaveCount(0);
});

it('does not flag a low rating older than the satisfaction window', function () {
    $customer = Customer::factory()->create();
    $customer->notes()->create(['body' => 'Recent touch']);
    $ticket = Ticket::factory()->for($customer)->create();
    backdate($ticket->satisfactionRating()->create(['rating' => 1]), now()->subDays(90));

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows)->toHaveCount(0);
});

it('excludes inactive and prospect customers entirely', function () {
    Customer::factory()->inactive()->create();

    $rows = app(ClientRadarService::class)->flaggedClients();

    expect($rows)->toHaveCount(0);
});
