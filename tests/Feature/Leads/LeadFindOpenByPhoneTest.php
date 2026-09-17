<?php

use App\Enums\LeadStatus;
use App\Models\Lead;

it('matches on the primary phone, in any of the stored formats', function (string $stored, string $lookup) {
    $lead = Lead::factory()->create(['phone' => $stored]);

    expect(Lead::findOpenByPhone($lookup)?->id)->toBe($lead->id);
})->with([
    'digits stored, digits looked up' => ['9876543210', '9876543210'],
    'plus-prefixed stored, digits looked up' => ['+919876543210', '9876543210'],
    'digits stored, plus-prefixed looked up' => ['9876543210', '+919876543210'],
]);

it('also matches on alternate_phone -- mirrors Customer::findByPhone()\'s own existing precedent', function () {
    // Real gap, lead #445/#446 (Babban Verama, 2026-09-17): a WhatsApp
    // relay message taught the CRM a second, form-typed number for a lead
    // already created under its real WhatsApp-sending number. Without
    // this, ImportMetaLead::handle()'s own race-condition lookup (which
    // searches by the number MET's webhook itself reports) could never
    // find that lead, and would create a duplicate every time instead.
    $lead = Lead::factory()->create(['phone' => '917408220959', 'alternate_phone' => '919823708625']);

    expect(Lead::findOpenByPhone('919823708625')?->id)->toBe($lead->id)
        ->and(Lead::findOpenByPhone('+919823708625')?->id)->toBe($lead->id);
});

it('never matches a Lost/Converted lead', function (LeadStatus $status) {
    Lead::factory()->create(['phone' => '9876543210', 'status' => $status]);

    expect(Lead::findOpenByPhone('9876543210'))->toBeNull();
})->with([
    'lost' => [LeadStatus::Lost],
    'converted' => [LeadStatus::Converted],
]);

it('returns null for a number matching neither phone nor alternate_phone', function () {
    Lead::factory()->create(['phone' => '9876543210', 'alternate_phone' => '9876500000']);

    expect(Lead::findOpenByPhone('9999999999'))->toBeNull();
});
