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
            $table->timestamp('won_at')->nullable()->after('next_follow_up_at');
        });

        // Backfill existing Won deals using updated_at as a best-effort proxy.
        DB::table('deals')
            ->where('stage', 'won')
            ->whereNull('won_at')
            ->update(['won_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('won_at');
        });
    }
};
