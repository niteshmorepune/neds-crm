<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same as leads.alternate_phone (2026-08-13) — a plain informational
        // second contact number, not used by any matching/lookup logic.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('alternate_phone')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('alternate_phone');
        });
    }
};
