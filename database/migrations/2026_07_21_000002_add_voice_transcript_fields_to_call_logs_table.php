<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('voice_transcript_status')->nullable()->after('notes');
            $table->text('voice_transcript')->nullable()->after('voice_transcript_status');
            $table->timestamp('voice_transcribed_at')->nullable()->after('voice_transcript');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn(['voice_transcript_status', 'voice_transcript', 'voice_transcribed_at']);
        });
    }
};
