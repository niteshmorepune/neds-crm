<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visibility_audit_funnel_events', function (Blueprint $table) {
            $table->timestamp('nudged_at')->nullable()->after('lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('visibility_audit_funnel_events', function (Blueprint $table) {
            $table->dropColumn('nudged_at');
        });
    }
};
