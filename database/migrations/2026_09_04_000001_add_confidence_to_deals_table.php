<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pipeline Playbook gap idea #3 (2026-09-04): a rep-set 1-10 gut
        // score on an open deal, sitting alongside the existing
        // stage-based weighted forecast rather than replacing it -- see
        // Deal::CONFIDENCE_MIN/MAX. Nullable -- most deals never get one
        // set, and that's the honest default, not a fabricated 0/5.
        Schema::table('deals', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('confidence');
        });
    }
};
