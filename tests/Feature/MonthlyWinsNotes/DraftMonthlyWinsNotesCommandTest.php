<?php

use App\Enums\CustomerStatus;
use App\Jobs\DraftMonthlyWinsNote;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

it('dispatches a job for each active, owned client', function () {
    Bus::fake();
    $owner = User::factory()->create();
    Customer::factory()->ownedBy($owner->id)->create();
    Customer::factory()->ownedBy($owner->id)->create();

    $this->artisan('app:draft-monthly-wins-notes', ['--month' => '2026-06'])->assertSuccessful();

    Bus::assertDispatchedTimes(DraftMonthlyWinsNote::class, 2);
});

it('skips clients without an owner', function () {
    Bus::fake();
    Customer::factory()->create(); // owner_id null

    $this->artisan('app:draft-monthly-wins-notes', ['--month' => '2026-06'])->assertSuccessful();

    Bus::assertNotDispatched(DraftMonthlyWinsNote::class);
});

it('skips inactive and prospect clients', function () {
    Bus::fake();
    $owner = User::factory()->create();
    Customer::factory()->ownedBy($owner->id)->create(['status' => CustomerStatus::Prospect]);
    Customer::factory()->ownedBy($owner->id)->inactive()->create();

    $this->artisan('app:draft-monthly-wins-notes', ['--month' => '2026-06'])->assertSuccessful();

    Bus::assertNotDispatched(DraftMonthlyWinsNote::class);
});

it('skips a client already drafted for the target month', function () {
    Bus::fake();
    $owner = User::factory()->create();
    $customer = Customer::factory()->ownedBy($owner->id)->create();
    Activity::create([
        'user_id' => null,
        'subject_type' => Customer::class,
        'subject_id' => $customer->id,
        'event' => DraftMonthlyWinsNote::ACTIVITY_EVENT,
        'changes' => ['month' => '2026-06'],
    ]);

    $this->artisan('app:draft-monthly-wins-notes', ['--month' => '2026-06'])->assertSuccessful();

    Bus::assertNotDispatched(DraftMonthlyWinsNote::class);
});

it('defaults to the month that just ended when --month is not given', function () {
    Bus::fake();
    $owner = User::factory()->create();
    Customer::factory()->ownedBy($owner->id)->create();

    $this->artisan('app:draft-monthly-wins-notes')->assertSuccessful();

    $expectedMonth = now()->subMonthNoOverflow()->format('Y-m');
    Bus::assertDispatched(DraftMonthlyWinsNote::class, fn ($job) => $job->monthKey === $expectedMonth);
});
