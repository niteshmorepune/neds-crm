<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live Google Meet join link ("Create Meeting"'s $result['meet_link'])
 * was previously only ever held in MeetingImport's own ephemeral
 * $createdMeetLink property, shown once right after creation and then lost
 * — never persisted on the Meeting row itself. Needed now so an invited
 * internal team member's notification/dashboard widget can offer a real
 * "Join Google Meet" link, not just a text description of the meeting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->text('meet_link')->nullable()->after('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('meet_link');
        });
    }
};
