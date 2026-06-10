<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // Auto-compiled metrics snapshot at submission time.
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('calls_made')->default(0);
            $table->unsignedInteger('leads_touched')->default(0);
            $table->string('attendance_status')->nullable();

            $table->text('summary')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
