<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('finds a lead by a phone number substring', function () {
    // Real gap, reported 2026-09-23: leads were not searchable by mobile
    // number at all on the Lead Generation list — the search filter only
    // ever checked name/company/email.
    $sales = User::factory()->role(UserRole::Sales)->create();
    $match = Lead::factory()->ownedBy($sales->id)->create(['name' => 'Priya Shah', 'phone' => '+91 98765 43210']);
    $other = Lead::factory()->ownedBy($sales->id)->create(['name' => 'Someone Else', 'phone' => '+91 90000 00000']);

    $response = $this->actingAs($sales)->get(route('leads.index', ['search' => '9876543210']));

    $ids = $response->viewData('leads')->pluck('id');
    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('finds a lead by an alternate phone number substring', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $match = Lead::factory()->ownedBy($sales->id)->create(['phone' => '+91 98765 43210', 'alternate_phone' => '+91 88888 77777']);

    $response = $this->actingAs($sales)->get(route('leads.index', ['search' => '8888877777']));

    expect($response->viewData('leads')->pluck('id'))->toContain($match->id);
});
