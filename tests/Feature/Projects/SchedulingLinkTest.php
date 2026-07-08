<?php

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;

it('prefers the lead assignee\'s scheduling link over the owner\'s', function () {
    $owner = User::factory()->create(['google_meet_scheduling_link' => 'https://cal.example/owner']);
    $lead = User::factory()->create(['google_meet_scheduling_link' => 'https://cal.example/lead']);
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $project->assignees()->attach($lead->id, ['role' => 'lead']);

    expect($project->schedulingContact()->id)->toBe($lead->id);
    expect($project->schedulingLink())->toBe('https://cal.example/lead');
});

it('falls back to the owner\'s link when there is no lead assignee', function () {
    $owner = User::factory()->create(['google_meet_scheduling_link' => 'https://cal.example/owner']);
    $member = User::factory()->create(['google_meet_scheduling_link' => 'https://cal.example/member']);
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $project->assignees()->attach($member->id, ['role' => 'member']);

    expect($project->schedulingContact()->id)->toBe($owner->id);
    expect($project->schedulingLink())->toBe('https://cal.example/owner');
});

it('falls back to the company-wide link when the primary contact has none set', function () {
    config(['company.meet_scheduling_link' => 'https://cal.example/company']);
    $owner = User::factory()->create(['google_meet_scheduling_link' => null]);
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    expect($project->schedulingLink())->toBe('https://cal.example/company');
});

it('returns null when neither the primary contact nor the company has a link', function () {
    config(['company.meet_scheduling_link' => '']);
    $owner = User::factory()->create(['google_meet_scheduling_link' => null]);
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    expect($project->schedulingLink())->toBeNull();
});

it('returns null when the project has no owner and no assignees', function () {
    config(['company.meet_scheduling_link' => '']);
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => null]);

    expect($project->schedulingContact())->toBeNull();
    expect($project->schedulingLink())->toBeNull();
});
