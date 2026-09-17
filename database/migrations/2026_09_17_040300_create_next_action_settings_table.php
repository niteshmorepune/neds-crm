<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row table holding the company-wide Next Action pop-up pause switch
 * (NextActionSetting::current(), same singleton pattern as billing_settings/
 * ai_usage_settings, not a generic key-value settings table). Deliberately
 * has no expiry/duration column — this is a plain on/off switch, not a
 * timed snooze (that already exists per-prompt via next_action_snoozes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_action_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('paused')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_action_settings');
    }
};
