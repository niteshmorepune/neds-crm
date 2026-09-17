<?php

use App\Enums\LeadGoal;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

/**
 * Render-level coverage for the Lead Generation list's "Next Action"
 * column. Shipped 2026-09-17 alongside "Latest Note" (phase 1 trial); the
 * owner retired that column 2026-09-18 once this one proved specific
 * enough on its own (still shown on the lead's own page, just not this
 * list). The rule logic itself is covered by LeadNextActionAdvisorTest —
 * this only checks the column is actually wired up: header present, a
 * real hint renders, Latest Note is genuinely gone from this page, and
 * the page still loads with no leads at all (the empty-state colspan).
 */
it('shows the Next Action column header and a real hint on the Lead Generation list, with no separate Latest Note column', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    Lead::factory()->create([
        'source' => LeadSource::ColdCall,
        'status' => LeadStatus::New,
        'goal' => LeadGoal::NotSure,
    ]);

    $this->actingAs($manager)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('Next Action')
        ->assertDontSee('Latest Note')
        ->assertSee('Needs Sales — book a meeting');
});

it('still renders the empty state correctly with the new column', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('leads.index'))
        ->assertOk()
        ->assertSee('No leads found.');
});
