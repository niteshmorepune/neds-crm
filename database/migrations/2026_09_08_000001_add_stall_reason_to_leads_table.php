<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 1 of the closure-guidance plan (2026-09-08, see CLAUDE.md
        // decisions log) -- a rep-set tag naming why a lead with real
        // conversation history isn't moving forward. Nullable, cleared
        // manually once resolved (no auto-clear on next contact -- the
        // point is a deliberate "this is handled" action, not a guess).
        Schema::table('leads', function (Blueprint $table) {
            $table->string('stall_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('stall_reason');
        });
    }
};
