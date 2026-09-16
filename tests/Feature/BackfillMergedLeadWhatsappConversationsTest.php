<?php

use App\Actions\MergeLeads;
use App\Models\Lead;
use App\Models\LeadWhatsappConversation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/**
 * Simulates the real historical shape this command exists to fix: before
 * MergeLeads::handle() recorded a lead_whatsapp_conversations mapping at
 * merge time (Task 2's own fix), a merge of two leads that each had their
 * own conversation left the duplicate's own conversation_id stranded with
 * no mapping row at all — exactly #421/#420 and #422/#423's real state.
 * Reproduced here by merging first, then deleting any mapping row the
 * (already-fixed) MergeLeads::handle() would have created, so the fixture
 * matches the pre-fix data these two real leads are still in today.
 */
function mergeWithoutMapping(Lead $primary, Lead $duplicate): void
{
    (new MergeLeads)->handle($primary, $duplicate, []);
    LeadWhatsappConversation::where('conversation_id', $duplicate->whatsapp_conversation_id)->delete();
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('reconstructs a mapping for a past merge that stranded the duplicate\'s conversation_id', function () {
    $primary = Lead::factory()->create(['name' => 'Pradyumna B Sevekari', 'whatsapp_conversation_id' => 'conv_primary']);
    $duplicate = Lead::factory()->create(['name' => 'Pradyumna Sevekari', 'whatsapp_conversation_id' => 'conv_duplicate']);
    mergeWithoutMapping($primary, $duplicate);

    expect(LeadWhatsappConversation::where('conversation_id', 'conv_duplicate')->exists())->toBeFalse();

    Artisan::call('app:backfill-merged-lead-whatsapp-conversations');

    $mapping = LeadWhatsappConversation::where('conversation_id', 'conv_duplicate')->first();
    expect($mapping)->not->toBeNull()
        ->and($mapping->lead_id)->toBe($primary->id);
});

it('dry-run reports what it would do without creating anything', function () {
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_primary_dry']);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_duplicate_dry']);
    mergeWithoutMapping($primary, $duplicate);

    Artisan::call('app:backfill-merged-lead-whatsapp-conversations', ['--dry-run' => true]);

    expect(LeadWhatsappConversation::where('conversation_id', 'conv_duplicate_dry')->exists())->toBeFalse()
        ->and(Artisan::output())->toContain('would map');
});

it('skips a merged-away lead that has since been restored — its own column already works, a mapping would wrongly redirect it', function () {
    // Real case: Lead #421 was merged into #420, then separately restored.
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_primary_restored_case']);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_duplicate_restored_case']);
    mergeWithoutMapping($primary, $duplicate);
    $duplicate->restore();

    Artisan::call('app:backfill-merged-lead-whatsapp-conversations');

    expect(LeadWhatsappConversation::where('conversation_id', 'conv_duplicate_restored_case')->exists())->toBeFalse();
});

it('skips a merge where the duplicate never had its own conversation_id to strand', function () {
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => null]);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_only_one']);
    mergeWithoutMapping($primary, $duplicate);

    // The "primary was null" path already correctly moved it onto primary —
    // nothing stranded, nothing for this command to do.
    expect($primary->fresh()->whatsapp_conversation_id)->toBe('conv_only_one');

    Artisan::call('app:backfill-merged-lead-whatsapp-conversations');

    expect(LeadWhatsappConversation::count())->toBe(0);
});

it('does not create a duplicate mapping row when run twice', function () {
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_primary_twice']);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'conv_duplicate_twice']);
    mergeWithoutMapping($primary, $duplicate);

    Artisan::call('app:backfill-merged-lead-whatsapp-conversations');
    Artisan::call('app:backfill-merged-lead-whatsapp-conversations');

    expect(LeadWhatsappConversation::where('conversation_id', 'conv_duplicate_twice')->count())->toBe(1);
});

it('skips a note that does not match the expected merge-breadcrumb shape, without throwing', function () {
    $lead = Lead::factory()->create();
    $lead->notes()->create(['user_id' => null, 'body' => 'Merged duplicate lead "No Id Here" into this record.']);

    expect(fn () => Artisan::call('app:backfill-merged-lead-whatsapp-conversations'))->not->toThrow(Throwable::class);
    expect(LeadWhatsappConversation::count())->toBe(0);
});

it('reconstructs mappings generally for multiple past merges in one run, not hardcoded to any specific leads', function () {
    $primaryA = Lead::factory()->create(['name' => 'Survivor One', 'whatsapp_conversation_id' => 'conv_a_primary']);
    $duplicateA = Lead::factory()->create(['name' => 'Merged One', 'whatsapp_conversation_id' => 'conv_a_duplicate']);
    mergeWithoutMapping($primaryA, $duplicateA);

    $primaryB = Lead::factory()->create(['name' => 'Survivor Two', 'whatsapp_conversation_id' => 'conv_b_primary']);
    $duplicateB = Lead::factory()->create(['name' => 'Merged Two', 'whatsapp_conversation_id' => 'conv_b_duplicate']);
    mergeWithoutMapping($primaryB, $duplicateB);

    Artisan::call('app:backfill-merged-lead-whatsapp-conversations');

    expect(LeadWhatsappConversation::where('conversation_id', 'conv_a_duplicate')->first()->lead_id)->toBe($primaryA->id)
        ->and(LeadWhatsappConversation::where('conversation_id', 'conv_b_duplicate')->first()->lead_id)->toBe($primaryB->id);
});
