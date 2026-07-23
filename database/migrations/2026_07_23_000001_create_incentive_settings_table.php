<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row table holding the admin-editable team-bonus pool for the Sales
 * Incentive feature (see incentive_statements). Modeled as a singleton via
 * IncentiveSetting::current() (firstOrCreate) rather than a generic
 * key-value settings table, since this is the only app-wide editable figure
 * of its kind so far.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_bonus_pool')->default(0); // paise
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_settings');
    }
};
