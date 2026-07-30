<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\QuarterlyAward;
use App\Models\User;

it('generates awards for the explicit --quarter option', function () {
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    Lead::factory()->count(3)->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    $this->artisan('app:generate-quarterly-awards', ['--quarter' => '2026-27-Q2'])->assertSuccessful();

    expect(QuarterlyAward::where('financial_year', '2026-27')->where('quarter', 2)->count())->toBeGreaterThan(0);
});

it('rejects a malformed --quarter option', function () {
    $this->artisan('app:generate-quarterly-awards', ['--quarter' => 'not-a-quarter'])->assertFailed();
});

it('defaults to the quarter that just ended when no option is given', function () {
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::create(2026, 8, 15)); // FY2026-27 Q2 -> previous is Q1

    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    Lead::factory()->count(3)->create(['owner_id' => $alice->id, 'converted_at' => Carbon\Carbon::create(2026, 5, 1)]);

    $this->artisan('app:generate-quarterly-awards')->assertSuccessful();

    expect(QuarterlyAward::where('financial_year', '2026-27')->where('quarter', 1)->count())->toBeGreaterThan(0);

    Illuminate\Support\Carbon::setTestNow();
});
