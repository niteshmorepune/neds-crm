<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadWhatsappConversation;
use App\Models\Note;
use Illuminate\Console\Command;

/**
 * One-off data migration: App\Actions\MergeLeads::handle() previously only
 * carried whatsapp_conversation_id onto the surviving Lead when the
 * survivor didn't already have one of its own — when BOTH merged Leads had
 * their own independently-live conversation, the duplicate's own
 * conversation_id was left stranded on the now-trashed record, permanently
 * unreachable by any future message on that conversation (real incidents
 * 2026-09-16: #421->#420 and #422->#423). MergeLeads::handle() itself is
 * now fixed to record this mapping going forward; this command
 * reconstructs it retroactively for every merge that already happened.
 *
 * Deliberately reads from the merge breadcrumb note MergeLeads::handle()
 * has always written on the surviving Lead ("Merged duplicate lead "X"
 * (#id) into this record."), not from possible_duplicate_of_lead_id — a
 * merge can happen between two Leads that were never auto-flagged as
 * duplicates by DuplicateLeadDetector, so the note text is the only
 * general, reliable record of every past merge, regardless of how it was
 * initiated.
 *
 * Skips a duplicate that is NOT currently trashed — if it was restored at
 * some point since the merge (by design or otherwise), its own
 * whatsapp_conversation_id column is already live and reachable through
 * the normal path; adding a mapping row on top would incorrectly redirect
 * future messages on that conversation to the primary instead of the
 * (correctly) active duplicate. Real case: Lead #421 was merged into #420,
 * then separately restored — it is NOT backfilled by this command,
 * correctly, since #421 itself already handles its own conversation fine.
 */
class BackfillMergedLeadWhatsappConversations extends Command
{
    protected $signature = 'app:backfill-merged-lead-whatsapp-conversations {--dry-run : Show what would be created without saving}';

    protected $description = 'Reconstruct lead_whatsapp_conversations mappings for past merges that stranded a duplicate lead\'s own conversation_id on a still-trashed record.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $mergeNotes = Note::where('notable_type', Lead::class)
            ->where('body', 'LIKE', 'Merged duplicate lead %')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($mergeNotes as $note) {
            if (! preg_match('/\(#(\d+)\)/', $note->body, $matches)) {
                $skipped++;

                continue;
            }

            $duplicateId = (int) $matches[1];
            $primary = Lead::find($note->notable_id);
            $duplicate = Lead::withTrashed()->find($duplicateId);

            if ($primary === null || $duplicate === null || ! $duplicate->trashed() || $duplicate->whatsapp_conversation_id === null) {
                $skipped++;

                continue;
            }

            if (LeadWhatsappConversation::where('conversation_id', $duplicate->whatsapp_conversation_id)->exists()) {
                $skipped++;

                continue;
            }

            $this->line(
                ($dryRun ? '[dry-run] would map' : 'Mapped')
                ." conversation {$duplicate->whatsapp_conversation_id} (Lead #{$duplicate->id} \"{$duplicate->name}\")"
                ." -> primary Lead #{$primary->id} \"{$primary->name}\""
            );

            if (! $dryRun) {
                LeadWhatsappConversation::create([
                    'lead_id' => $primary->id,
                    'conversation_id' => $duplicate->whatsapp_conversation_id,
                ]);
            }

            $created++;
        }

        $this->info(($dryRun ? 'Would create' : 'Created')." {$created} mapping(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
