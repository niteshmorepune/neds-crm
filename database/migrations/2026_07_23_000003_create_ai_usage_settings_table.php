<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row table holding the admin-editable monthly AI spend ceiling shown
 * on the AI Usage Report (see AiUsageSetting::current()) — a self-configured
 * budget warning in place of a real "credit balance" figure, since Anthropic
 * doesn't expose a public API to check remaining prepaid credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monthly_budget_paise')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_settings');
    }
};
