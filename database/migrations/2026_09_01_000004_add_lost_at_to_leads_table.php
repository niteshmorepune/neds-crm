<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors converted_at's own shape for the other terminal outcome --
        // stamped by Lead::booted()'s saving() hook (same pattern as
        // Task::completed_at) the moment status transitions to Lost, cleared
        // if ever reopened. Going-forward only: a Lead already Lost before
        // this shipped has no lost_at and won't be backfilled from
        // updated_at, which could easily reflect an unrelated later edit
        // rather than the actual date it was lost -- the Score Calibration
        // report simply won't include those older leads in a date-range
        // view, rather than risk a silently wrong date.
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('lost_at')->nullable()->after('converted_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lost_at');
        });
    }
};
