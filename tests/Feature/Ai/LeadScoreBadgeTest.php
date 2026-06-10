<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('shows the AI score badge and reason on the lead page when scored', function () {
    $lead = Lead::factory()->create(['ai_score' => 88, 'ai_score_reason' => 'Clear budget and intent']);

    $this->actingAs($this->admin)->get(route('leads.show', $lead))->assertOk()
        ->assertSee('AI 88')
        ->assertSee('Clear budget and intent');
});

it('shows no badge when the lead is unscored', function () {
    $lead = Lead::factory()->create(['ai_score' => null, 'ai_score_reason' => null]);

    $this->actingAs($this->admin)->get(route('leads.show', $lead))->assertOk()
        ->assertDontSee('class="inline-flex items-center gap-1 rounded-full', false);
});
