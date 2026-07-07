<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Referred by (agency)" on the client directly, not just on a Deal
 * (deals.partner_id already existed) — clients imported straight from CSV
 * never went through a Lead/Deal, so they had no way to record a referring
 * partner. Nullable, independent of any deal history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('referring_partner_id')->nullable()->after('owner_id')
                ->constrained('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referring_partner_id']);
            $table->dropColumn('referring_partner_id');
        });
    }
};
