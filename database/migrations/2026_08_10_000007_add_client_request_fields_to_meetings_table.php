<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Panel Tier 1: clients can now request a meeting directly from the
 * portal (Portal\ProjectController::requestMeeting()). requested_by_client
 * distinguishes these from staff-created meetings on the internal
 * Customer show page's Meetings list; client_note carries the client's own
 * context for the request (e.g. what they want to discuss) — separate
 * from raw_transcript, which is meeting notes/transcript content, not a
 * pre-meeting request note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('requested_by_client')->default(false)->after('meetable_id');
            $table->text('client_note')->nullable()->after('requested_by_client');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['requested_by_client', 'client_note']);
        });
    }
};
