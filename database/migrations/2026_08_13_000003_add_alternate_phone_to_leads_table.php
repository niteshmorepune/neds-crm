<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A second contact number for the lead (e.g. WhatsApp vs. office
        // line). Deliberately informational only — the cross-channel
        // duplicate-lead matching in Lead::findOpenByPhone() only checks the
        // primary `phone` column, not this one; revisit only if that
        // actually causes missed duplicate matches in practice.
        Schema::table('leads', function (Blueprint $table) {
            $table->string('alternate_phone')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('alternate_phone');
        });
    }
};
