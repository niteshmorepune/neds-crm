<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the Google Meet Notes integration — appends the Claude-generated
 * summary on top of Phase 1's raw_transcript, mirroring call_logs' own
 * voice_transcript_status/voice_transcript/voice_transcribed_at shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('ai_summary_status')->nullable()->after('raw_transcript');
            $table->longText('ai_summary')->nullable()->after('ai_summary_status');
            $table->timestamp('ai_summarized_at')->nullable()->after('ai_summary');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['ai_summary_status', 'ai_summary', 'ai_summarized_at']);
        });
    }
};
