<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_nudge_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_nudge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->string('status')->default('pending');
            $table->string('completed_via')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('snoozed_until')->nullable();
            $table->timestamps();

            $table->unique(['team_nudge_id', 'user_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_nudge_statuses');
    }
};
