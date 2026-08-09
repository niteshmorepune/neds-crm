<?php

use App\Enums\MeetingParticipantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal team members invited to a client/lead meeting alongside the
 * client (see MeetingImport::createMeeting()) — separate from Meeting's own
 * `attendees` JSON column, which is a denormalized display list of names/
 * emails pulled straight from the Google Calendar event and was never meant
 * to support per-person status tracking (Accepted/Pending/Declined).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(MeetingParticipantStatus::Pending->value);
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
