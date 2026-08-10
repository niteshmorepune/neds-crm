<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner Panel Tier 1: a per-partner commission rate (percentage of a
 * referred deal's pre-tax value, paid at Won) — nullable, since real
 * commission arrangements vary by partner and most partners today have
 * none at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
