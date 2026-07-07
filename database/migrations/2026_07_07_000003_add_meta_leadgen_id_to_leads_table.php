<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase E of the AI lead funnel: Meta Lead Ads webhook. Dedupe key for the
 * ImportMetaLead job — Meta's webhook can redeliver the same leadgen_id, and
 * this stops a redelivery from creating a second Lead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('meta_leadgen_id')->nullable()->unique()->after('utm_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('meta_leadgen_id');
        });
    }
};
