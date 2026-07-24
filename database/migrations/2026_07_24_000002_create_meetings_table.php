<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the Google Meet Notes integration — imported meeting metadata
 * plus the raw transcript/recording links Google Meet itself already
 * generates. Deliberately its own table (like call_logs), not the generic
 * polymorphic Note system, since a meeting has real structured fields worth
 * querying. Attach scope is Customer + Lead only, mirroring CallLog's
 * `callable` exactly (confirmed with the owner). No `ai_summary` column yet
 * — that's a Phase 2 append-only migration once this is proven live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('meetable'); // customer or lead
            $table->string('google_event_id')->unique();
            $table->string('title');
            $table->timestamp('occurred_at');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('attendees')->nullable();
            $table->text('drive_recording_url')->nullable();
            $table->text('drive_transcript_url')->nullable();
            $table->longText('raw_transcript')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
