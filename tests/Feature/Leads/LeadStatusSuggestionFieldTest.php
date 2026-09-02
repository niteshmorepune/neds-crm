<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Livewire\LeadStatusSuggestion;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('suggests a status when the Suggest button is clicked', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'The call was not answered by the client.']);
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['status' => 'contacted', 'rationale' => 'A real call attempt was made.'])]],
        'usage' => ['input_tokens' => 30, 'output_tokens' => 10],
    ])]);

    Livewire::actingAs($admin)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->call('suggest')
        ->assertSet('suggestedStatus', 'contacted')
        ->assertSet('selectedStatus', 'contacted')
        ->assertSet('rationale', 'A real call attempt was made.')
        ->assertSee('A real call attempt was made.');
});

it('does not call Claude a second time if suggest is called twice', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['status' => 'contacted', 'rationale' => 'Attempted.'])]],
        'usage' => ['input_tokens' => 30, 'output_tokens' => 10],
    ])]);

    Livewire::actingAs($admin)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->call('suggest')
        ->call('suggest');

    Http::assertSentCount(1);
});

it('shows a helpful message and no pre-selection when the model returns nothing usable', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Talked briefly.']);
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => 'not json at all']],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ])]);

    Livewire::actingAs($admin)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->call('suggest')
        ->assertSet('suggestedStatus', null)
        ->assertSee('No suggestion available');
});

it('forbids suggesting for a lead the viewer cannot update', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherOwner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($otherOwner->id)->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    Livewire::actingAs($sales)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->call('suggest')
        ->assertForbidden();
});

it('applies the selected status and redirects back to the lead', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    Livewire::actingAs($admin)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->set('selectedStatus', 'contacted')
        ->call('apply')
        ->assertRedirect(route('leads.show', $lead));

    expect($lead->fresh()->status)->toBe(LeadStatus::Contacted);
});

it('lets the rep override the suggested status before applying', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Client said not interested.']);
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['status' => 'contacted', 'rationale' => 'A call was made.'])]],
        'usage' => ['input_tokens' => 30, 'output_tokens' => 10],
    ])]);

    Livewire::actingAs($admin)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->call('suggest')
        ->assertSet('suggestedStatus', 'contacted')
        ->set('selectedStatus', 'lost') // rep disagrees with the AI, picks Lost instead
        ->call('apply');

    expect($lead->fresh()->status)->toBe(LeadStatus::Lost);
});

it('rejects applying with nothing selected', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    Livewire::actingAs($admin)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->call('apply')
        ->assertHasErrors('selectedStatus');

    expect($lead->fresh()->status)->toBe(LeadStatus::New);
});

it('forbids applying for a lead the viewer cannot update', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $otherOwner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($otherOwner->id)->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    Livewire::actingAs($sales)
        ->test(LeadStatusSuggestion::class, ['lead' => $lead])
        ->set('selectedStatus', 'contacted')
        ->call('apply')
        ->assertForbidden();

    expect($lead->fresh()->status)->toBe(LeadStatus::New);
});
