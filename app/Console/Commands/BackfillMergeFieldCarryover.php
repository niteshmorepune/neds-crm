<?php

namespace App\Console\Commands;

use App\Actions\CarryOverMergeFields;
use App\Models\Lead;
use App\Models\Note;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * One-off data correction, mirroring App\Console\Commands\
 * BackfillMergedLeadWhatsappConversations in both shape and care: App\Actions\
 * MergeLeads::handle() previously never carried real business-data fields
 * (Meta attribution, the offer-recommendation state, UTM, goal/budget,
 * website/GBP links, a scheduled follow-up, telecaller assignment) from a
 * merged-away duplicate onto the surviving primary — MergeLeadsRequest::
 * MERGEABLE_FIELDS never offered a rep a choice on any of them, and nothing
 * else carried them over either. A real production investigation
 * (2026-09-16) confirmed 15 of 38 historical merges silently dropped at
 * least one of these fields. App\Actions\CarryOverMergeFields now fixes
 * this going forward inside MergeLeads::handle() itself; this command
 * reconstructs it retroactively for every merge that already happened.
 *
 * Deliberately does NOT hardcode the 15 affected merge ids — it walks
 * every merge breadcrumb note (same detection BackfillMergedLeadWhatsapp
 * Conversations already uses: "Merged duplicate lead "X" (#id) into this
 * record.", the only general record of every past merge regardless of how
 * it was initiated) and asks CarryOverMergeFields itself, read-only,
 * whether that specific pair has anything left to carry over. This makes
 * the command a real, re-runnable audit tool: run it again after any
 * future manual data-entry backfill and it will only report/act on
 * whatever is still actually missing, not a frozen list from today.
 *
 * Idempotent by construction, not by a special case: CarryOverMergeFields
 * only ever fills a field the primary doesn't already have (or, for
 * meta_leadgen_id/the recommendation bundle, only when the primary has no
 * value of its own — see its own docblock for why #346/#411's specific
 * recommendation_token loss is a genuine, flagged exception to this, not a
 * bug). #420/#421 (already corrected by a separate one-off script) is
 * naturally skipped for the same reason, not excluded by id.
 *
 * Skips a duplicate that is NOT currently trashed (restored at some point
 * since the merge — its own fields are live, independent data again, not
 * safe to fold into the primary) and a self-referential note (the
 * primary_id/duplicate_id parsed from the note happen to be the same
 * Lead — a real, harmless artifact found in production from the
 * 2026-09-16 restore-and-remerge churn around #285/#420/#421, not a
 * distinct pair with anything to carry over).
 */
class BackfillMergeFieldCarryover extends Command
{
    protected $signature = 'app:backfill-merge-field-carryover {--dry-run : Show what would change without saving}';

    protected $description = 'Retroactively carry real business-data fields (Meta attribution, recommendation state, UTM, goal/budget, links, follow-up, telecaller) from every past merge\'s duplicate onto its surviving primary, wherever the primary is still missing them.';

    public function handle(CarryOverMergeFields $carryOverMergeFields): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $mergeNotes = Note::where('notable_type', Lead::class)
            ->where('body', 'LIKE', 'Merged duplicate lead %')
            ->orderBy('created_at')
            ->get();

        $affected = 0;
        $unaffected = 0;
        $skippedSelfReferential = 0;
        $skippedMissing = 0;
        $skippedNotTrashed = 0;

        foreach ($mergeNotes as $note) {
            if (! preg_match('/\(#(\d+)\)/', $note->body, $matches)) {
                $skippedMissing++;

                continue;
            }

            $duplicateId = (int) $matches[1];
            $primary = Lead::withTrashed()->find($note->notable_id);
            $duplicate = Lead::withTrashed()->find($duplicateId);

            if ($primary === null || $duplicate === null) {
                $skippedMissing++;

                continue;
            }

            if ($primary->is($duplicate)) {
                $skippedSelfReferential++;

                continue;
            }

            if (! $duplicate->trashed()) {
                $skippedNotTrashed++;

                continue;
            }

            $changes = $carryOverMergeFields->handle($primary, $duplicate, dryRun: $dryRun);

            if ($changes === []) {
                $unaffected++;

                continue;
            }

            $affected++;
            $fieldList = implode(', ', array_map(
                fn ($field, $value) => "{$field}=".$this->stringify($value),
                array_keys($changes),
                $changes,
            ));
            $this->line(
                ($dryRun ? '[dry-run] would carry over' : 'Carried over')
                ." onto primary Lead #{$primary->id} \"{$primary->name}\" from duplicate #{$duplicate->id} \"{$duplicate->name}\": {$fieldList}"
            );
        }

        $this->info(
            ($dryRun ? '[dry-run] Would affect' : 'Affected')
            ." {$affected} merge(s); {$unaffected} already had nothing missing; "
            ."skipped {$skippedSelfReferential} self-referential, {$skippedNotTrashed} restored (not trashed), {$skippedMissing} unparseable/missing "
            .'(out of '.$mergeNotes->count().' total merge record(s)).'
        );

        return self::SUCCESS;
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
