<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Partner;
use App\Models\PartnerCommissionStatement;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows a live commission estimate on the partner show page when a rate is set', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => now(),
        'value' => 50_000 * 100,
    ]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSee('Commission')
        ->assertSee('5,000'); // 10% of ₹50,000
});

it('hides the commission section entirely when no rate is set and no history exists', function () {
    $partner = Partner::factory()->create(['commission_rate' => null]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.show', $partner))
        ->assertOk()
        ->assertDontSee('Commission');
});

it('lets an admin mark a commission statement as paid', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    $statement = PartnerCommissionStatement::factory()->create([
        'partner_id' => $partner->id,
        'paid_at' => null,
        'paid_by' => null,
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($admin)
        ->post(route('partners.commission-statements.mark-paid', [$partner, $statement]))
        ->assertRedirect();

    $statement->refresh();
    expect($statement->paid_at)->not->toBeNull()
        ->and($statement->paid_by)->toBe($admin->id);
});

it('forbids a sales user from marking a commission statement as paid', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    $statement = PartnerCommissionStatement::factory()->create(['partner_id' => $partner->id]);

    actingAs(User::factory()->create(['role' => UserRole::Sales]))
        ->post(route('partners.commission-statements.mark-paid', [$partner, $statement]))
        ->assertForbidden();
});

it('404s when the statement does not belong to the given partner', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    $otherPartner = Partner::factory()->create(['commission_rate' => 10]);
    $statement = PartnerCommissionStatement::factory()->create(['partner_id' => $otherPartner->id]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.commission-statements.mark-paid', [$partner, $statement]))
        ->assertNotFound();
});
