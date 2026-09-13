<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable cooldown state for App\Console\Commands\MonitorOfferFunnelFailures
 * — one row per distinct alert type ("incident"), keyed by alert_key,
 * tracking when it last fired so a persisting spike doesn't re-alert every
 * hour. Deliberately a small, generic table (not a Lead/Deal-scoped column
 * like every other "nudged_at"-style marker in this app) since a systemic
 * failure spike has no single record to attach a marker to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key')->unique();
            $table->timestamp('last_alerted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
