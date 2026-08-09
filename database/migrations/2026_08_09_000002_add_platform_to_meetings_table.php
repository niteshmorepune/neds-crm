<?php

use App\Enums\MeetingPlatform;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports logging a meeting the client organized on an external platform
 * (Zoom, Microsoft Teams, etc.) — not just meetings created/imported through
 * NEDS's own Google Meet integration. Every existing row is a real Google
 * Meet import, so the column defaults there; a manually-logged meeting sets
 * it explicitly and gets a synthetic google_event_id (still required/unique,
 * deliberately not made nullable — see MeetingImport::saveManualMeeting())
 * since there's no real Google Calendar event backing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('platform')->default(MeetingPlatform::GoogleMeet->value)->after('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
