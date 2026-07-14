<?php

use App\Enums\CustomerStatus;
use App\Jobs\ProvisionClientExternallyJob;
use App\Models\Customer;
use Illuminate\Support\Facades\Queue;

it('queues provisioning only for active customers missing both external ids', function () {
    Queue::fake();

    $needsBackfill = Customer::factory()->create(['status' => CustomerStatus::Active, 'drishti_client_id' => null, 'smdost_client_id' => null]);
    $alreadyProvisioned = Customer::factory()->create(['status' => CustomerStatus::Active, 'drishti_client_id' => 'drishti-1', 'smdost_client_id' => 'smdost-1']);
    $partiallyProvisioned = Customer::factory()->create(['status' => CustomerStatus::Active, 'drishti_client_id' => 'drishti-2', 'smdost_client_id' => null]);
    $prospect = Customer::factory()->create(['status' => CustomerStatus::Prospect, 'drishti_client_id' => null, 'smdost_client_id' => null]);

    $this->artisan('app:backfill-client-provisioning')->assertSuccessful();

    Queue::assertPushed(ProvisionClientExternallyJob::class, 1);
    Queue::assertPushed(ProvisionClientExternallyJob::class, fn ($job) => $job->customerId === $needsBackfill->id);
    Queue::assertNotPushed(ProvisionClientExternallyJob::class, fn ($job) => $job->customerId === $alreadyProvisioned->id);
    Queue::assertNotPushed(ProvisionClientExternallyJob::class, fn ($job) => $job->customerId === $partiallyProvisioned->id);
    Queue::assertNotPushed(ProvisionClientExternallyJob::class, fn ($job) => $job->customerId === $prospect->id);
});
