<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Reproduces the real historical shape this command exists to fix: before
 * App\Actions\CarryOverMergeFields existed, MergeLeads::handle() never
 * carried these fields at all -- merges from that era left the duplicate's
 * real data stranded on the trashed record with a merge breadcrumb note as
 * the only trace. Reproduced by directly creating the note + trashed
 * duplicate (with its real field values still present) rather than merging
 * through the now-fixed MergeLeads::handle(), which would carry everything
 * over immediately and leave nothing for this command to find.
 */
function stubHistoricalMerge(Lead $primary, Lead $duplicate): Note
{
    $duplicate->delete();

    return $primary->notes()->create([
        'user_id' => null,
        'body' => "Merged duplicate lead \"{$duplicate->name}\" (#{$duplicate->id}) into this record.",
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
});

it('carries over the missing fields for a historical merge, matching the real 15-affected-merge shape', function () {
    $primary = Lead::factory()->create(['goal' => null, 'utm_source' => null, 'meta_leadgen_id' => null]);
    $duplicate = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'utm_source' => 'meta', 'meta_leadgen_id' => 'lg_'.Str::random(10),
    ]);
    stubHistoricalMerge($primary, $duplicate);

    Artisan::call('app:backfill-merge-field-carryover');

    $primary->refresh();
    expect($primary->goal)->toBe(LeadGoal::GenerateLeads)
        ->and($primary->utm_source)->toBe('meta')
        ->and($primary->meta_leadgen_id)->not->toBeNull();
});

it('dry-run reports what it would change without writing anything', function () {
    $primary = Lead::factory()->create(['goal' => null]);
    $duplicate = Lead::factory()->create(['goal' => LeadGoal::RankHigher]);
    stubHistoricalMerge($primary, $duplicate);

    Artisan::call('app:backfill-merge-field-carryover', ['--dry-run' => true]);
    $output = Artisan::output();

    expect($primary->fresh()->goal)->toBeNull()
        ->and($output)->toContain('would carry over')
        ->and($output)->toContain('goal=');
});

it('explicitly includes the #346/#411 shape: recovers next_follow_up_at but leaves the primary\'s own different recommendation_token untouched', function () {
    $primaryToken = (string) Str::uuid();
    $primary = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_key' => 'lead-generation-audit', 'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => $primaryToken, 'recommendation_generated_at' => now()->subDays(4),
        'next_follow_up_at' => null,
    ]);
    $duplicateToken = (string) Str::uuid();
    $duplicate = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_key' => 'lead-generation-audit', 'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => $duplicateToken, 'recommendation_generated_at' => now()->subDay(),
        'next_follow_up_at' => now()->addDays(3),
    ]);
    stubHistoricalMerge($primary, $duplicate);

    Artisan::call('app:backfill-merge-field-carryover');

    $primary->refresh();
    expect($primary->next_follow_up_at)->not->toBeNull()
        ->and($primary->recommendation_token)->toBe($primaryToken)
        ->and($primary->recommendation_token)->not->toBe($duplicateToken);
});

it('leaves an already-correct pair (the #420/#421 shape) with nothing to do, naturally idempotent, no id exclusion needed', function () {
    $primary = Lead::factory()->create([
        'goal' => LeadGoal::GrowBusiness, 'budget_range' => LeadBudgetRange::ThreeToSix,
        'website_url' => 'https://already-corrected.example.com', 'utm_source' => 'meta',
        'meta_leadgen_id' => 'lg_already_corrected',
    ]);
    // The duplicate here stands in for #420 AFTER the one-off correction
    // script already ran: its own unique fields are already nulled, so
    // there's genuinely nothing left for this command to move.
    $duplicate = Lead::factory()->create([
        'goal' => null, 'budget_range' => null, 'website_url' => null, 'utm_source' => null,
        'meta_leadgen_id' => null,
    ]);
    stubHistoricalMerge($primary, $duplicate);

    Artisan::call('app:backfill-merge-field-carryover');

    expect(Artisan::output())->toContain('Affected 0 merge')
        ->and($primary->fresh()->website_url)->toBe('https://already-corrected.example.com');
});

it('skips a self-referential merge note without throwing', function () {
    $lead = Lead::factory()->create();
    $lead->notes()->create(['user_id' => null, 'body' => "Merged duplicate lead \"{$lead->name}\" (#{$lead->id}) into this record."]);

    expect(fn () => Artisan::call('app:backfill-merge-field-carryover'))->not->toThrow(Throwable::class);
    expect(Artisan::output())->toContain('skipped 1 self-referential');
});

it('skips a merged-away lead that has since been restored — its own fields are live, independent data again', function () {
    $primary = Lead::factory()->create(['goal' => null]);
    $duplicate = Lead::factory()->create(['goal' => LeadGoal::GenerateLeads]);
    $note = stubHistoricalMerge($primary, $duplicate);
    $duplicate->restore();
    // Restored, and has since moved on independently — a real scenario,
    // not just a mechanical restore.
    $duplicate->update(['goal' => LeadGoal::RankHigher]);

    Artisan::call('app:backfill-merge-field-carryover');

    expect($primary->fresh()->goal)->toBeNull();
    expect($note)->not->toBeNull();
});

it('skips a note that does not match the expected merge-breadcrumb shape, without throwing', function () {
    $lead = Lead::factory()->create();
    $lead->notes()->create(['user_id' => null, 'body' => 'Merged duplicate lead "No Id Here" into this record.']);

    expect(fn () => Artisan::call('app:backfill-merge-field-carryover'))->not->toThrow(Throwable::class);
});

it('re-derives affected merges generally across multiple pairs in one run, not hardcoded to any specific leads', function () {
    $primaryA = Lead::factory()->create(['name' => 'Survivor One', 'goal' => null]);
    $duplicateA = Lead::factory()->create(['name' => 'Merged One', 'goal' => LeadGoal::GenerateLeads]);
    stubHistoricalMerge($primaryA, $duplicateA);

    $primaryB = Lead::factory()->create(['name' => 'Survivor Two', 'utm_source' => null]);
    $duplicateB = Lead::factory()->create(['name' => 'Merged Two', 'utm_source' => 'meta']);
    stubHistoricalMerge($primaryB, $duplicateB);

    Artisan::call('app:backfill-merge-field-carryover');

    expect($primaryA->fresh()->goal)->toBe(LeadGoal::GenerateLeads)
        ->and($primaryB->fresh()->utm_source)->toBe('meta')
        ->and(Artisan::output())->toContain('Affected 2 merge');
});

it('does not double-apply or throw a unique-constraint violation when run a second time — real idempotency, not a dry-run claim', function () {
    $primary = Lead::factory()->create(['meta_leadgen_id' => null]);
    $duplicate = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.Str::random(10)]);
    $metaLeadgenId = $duplicate->meta_leadgen_id;
    stubHistoricalMerge($primary, $duplicate);

    Artisan::call('app:backfill-merge-field-carryover');
    expect($primary->fresh()->meta_leadgen_id)->toBe($metaLeadgenId);

    expect(fn () => Artisan::call('app:backfill-merge-field-carryover'))->not->toThrow(Throwable::class);
    expect(Artisan::output())->toContain('Affected 0 merge');
    expect($primary->fresh()->meta_leadgen_id)->toBe($metaLeadgenId);
});
