<?php

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;

it('shows the Schedule a Meeting callout when the project resolves a scheduling link', function () {
    $owner = User::factory()->create(['google_meet_scheduling_link' => 'https://cal.example/owner']);
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id]);
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.projects.show', $project))
        ->assertOk()
        ->assertSee('Schedule a Meeting')
        ->assertSee('https://cal.example/owner', false);
});

it('hides the Schedule a Meeting callout when no scheduling link resolves', function () {
    config(['company.meet_scheduling_link' => '']);
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => null]);
    $contact = Contact::factory()->portalUser()->create(['customer_id' => $customer->id]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.projects.show', $project))
        ->assertOk()
        ->assertDontSee('Schedule a Meeting');
});
