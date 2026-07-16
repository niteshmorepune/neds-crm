<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('stage_changed_at')->nullable()->after('won_at');
        });

        // Backfill using updated_at as a best-effort proxy — same approach as
        // the won_at backfill above. Only accurate for deals untouched since
        // they entered their current stage; going forward the saving() hook
        // keeps this exact.
        DB::table('deals')
            ->whereNull('stage_changed_at')
            ->update(['stage_changed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('stage_changed_at');
        });
    }
};
