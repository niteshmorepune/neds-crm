<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;

beforeEach(function () {
    config(['services.lead_capture.token' => 'secret-token']);
});

it('rejects requests without a token', function () {
    $this->postJson('/api/leads', ['name' => 'No Token'])->assertStatus(401);
    expect(Lead::count())->toBe(0);
});

it('rejects requests with a wrong token', function () {
    $this->postJson('/api/leads', ['name' => 'Bad Token', 'email' => 'a@b.test'], [
        'Authorization' => 'Bearer wrong',
    ])->assertStatus(401);
});

it('creates an unassigned website lead with a valid token', function () {
    $this->postJson('/api/leads', [
        'name' => 'Web Visitor',
        'company' => 'Visitor Co',
        'email' => 'visitor@site.test',
        'message' => 'Interested in SEO',
    ], ['Authorization' => 'Bearer secret-token'])
        ->assertCreated()
        ->assertJsonStructure(['message', 'id']);

    $lead = Lead::firstWhere('email', 'visitor@site.test');

    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe(LeadSource::Website)
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->owner_id)->toBeNull()
        ->and($lead->notes()->count())->toBe(1);
});

it('also accepts the X-Lead-Token header', function () {
    $this->postJson('/api/leads', ['name' => 'Hdr', 'phone' => '9999999999'], [
        'X-Lead-Token' => 'secret-token',
    ])->assertCreated();
});

it('validates the payload', function () {
    // Missing name.
    $this->postJson('/api/leads', ['email' => 'a@b.test'], ['Authorization' => 'Bearer secret-token'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    // Name but neither email nor phone.
    $this->postJson('/api/leads', ['name' => 'Solo'], ['Authorization' => 'Bearer secret-token'])
        ->assertStatus(422)->assertJsonValidationErrors(['email', 'phone']);
});
