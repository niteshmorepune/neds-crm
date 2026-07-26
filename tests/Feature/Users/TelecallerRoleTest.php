<?php

use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a telecaller reach the dashboard, leads, and calling', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    $this->actingAs($telecaller)->get(route('dashboard'))->assertOk()
        ->assertSee('New leads to call');
    $this->actingAs($telecaller)->get(route('leads.index'))->assertOk();
    $this->actingAs($telecaller)->get(route('calls.index'))->assertOk();
});

it('denies a telecaller every module outside leads and calling, at the route AND policy level', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    // Route level (menu.access middleware / resource policy gate).
    $this->actingAs($telecaller)->get(route('deals.index'))->assertForbidden();
    $this->actingAs($telecaller)->get(route('quotations.index'))->assertForbidden();
    $this->actingAs($telecaller)->get(route('invoices.index'))->assertForbidden();
    $this->actingAs($telecaller)->get(route('incentives.index'))->assertForbidden();
    $this->actingAs($telecaller)->get(route('tickets.index'))->assertForbidden();
    $this->actingAs($telecaller)->get(route('clients.index'))->assertForbidden();

    // Policy level, independent of the menu/route gate above — proves the
    // underlying permission is actually closed, not just the sidebar link.
    expect($telecaller->can('create', Deal::class))->toBeFalse()
        ->and($telecaller->can('create', Quotation::class))->toBeFalse()
        ->and($telecaller->can('create', Invoice::class))->toBeFalse()
        ->and($telecaller->can('viewAny', Ticket::class))->toBeFalse();
});

it('lets a telecaller log a call against a lead', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($telecaller)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    expect(CallLog::where('user_id', $telecaller->id)->where('callable_id', $lead->id)->exists())->toBeTrue();
});

it('allows telecaller as an additional role without a database error (role_user enum includes it)', function () {
    $user = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Telecaller)->create();

    expect($user->allRoles()->pluck('value'))->toContain('telecaller');
    expect($user->hasRole(UserRole::Telecaller))->toBeTrue();
});
