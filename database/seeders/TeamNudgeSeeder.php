<?php

namespace Database\Seeders;

use App\Enums\NudgeAutoDetectType;
use App\Enums\NudgeRecurrence;
use App\Enums\UserRole;
use App\Models\TeamNudge;
use Illuminate\Database\Seeder;

/**
 * The 3 nudges agreed for the adoption-gap closing plan (see CLAUDE.md
 * decisions log / project memory backlog.md's "LOCKED-IN next-work
 * sequence"). Idempotent via updateOrCreate keyed on title, so re-running
 * (e.g. after adding a 4th nudge here later) never duplicates these.
 */
class TeamNudgeSeeder extends Seeder
{
    public function run(): void
    {
        $nudges = [
            [
                'title' => 'Record staff training videos',
                'description' => 'Scripts are already written — record and share them so the team can actually learn the CRM.',
                'target_role' => UserRole::Admin->value,
                'recurrence' => NudgeRecurrence::OneTime->value,
                'auto_detect_type' => null,
                'due_date' => null,
            ],
            [
                'title' => 'Log every active client relationship as a Deal',
                'description' => 'Even for existing clients that never got one — retroactively, so the pipeline reflects reality.',
                'target_role' => UserRole::Sales->value,
                'recurrence' => NudgeRecurrence::OneTime->value,
                'auto_detect_type' => null,
                'due_date' => null,
            ],
            [
                'title' => 'Route every client issue through Tickets',
                'description' => 'Even ones resolved in a 2-minute WhatsApp reply — this is what feeds CSAT/SLA/Client Radar.',
                'target_role' => UserRole::Support->value,
                'recurrence' => NudgeRecurrence::Weekly->value,
                'auto_detect_type' => NudgeAutoDetectType::TicketsLoggedThisWeek->value,
                'due_date' => null,
            ],
        ];

        foreach ($nudges as $data) {
            TeamNudge::updateOrCreate(
                ['title' => $data['title']],
                $data + ['is_active' => true, 'created_by' => null],
            );
        }
    }
}
