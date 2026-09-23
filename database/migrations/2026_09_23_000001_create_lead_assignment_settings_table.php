<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row table holding the company-wide "force all new leads to one
 * Sales rep" switch (LeadAssignmentSetting::current(), same singleton
 * pattern as next_action_settings/billing_settings/ai_usage_settings, not a
 * generic key-value settings table). Checked first in LeadObserver::
 * autoAssign(), ahead of LeadAssignmentRule matching and the least-loaded
 * round-robin fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_assignment_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->foreignId('forced_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assignment_settings');
    }
};
