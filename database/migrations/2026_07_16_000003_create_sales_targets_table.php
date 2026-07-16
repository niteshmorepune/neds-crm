<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin/manager-set revenue targets for the Sales Dashboard. `user_id` null
 * = a company-wide target; set = a per-rep target. `period_start` is the
 * first day of the month (period_type=month) or of the financial year
 * (period_type=financial_year, i.e. always an April 1). All writes go
 * through SalesTargetController's updateOrCreate — the unique index below
 * only enforces uniqueness for a given rep (MySQL treats each NULL user_id
 * as distinct, so it can't enforce "one company target per period" at the
 * DB level; that's guaranteed by always writing through the same keyed
 * updateOrCreate call instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('period_type');
            $table->date('period_start');
            $table->integer('target_value');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
