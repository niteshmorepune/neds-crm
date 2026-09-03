<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_action_snoozes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_key');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('snoozed_until');
            $table->timestamps();

            $table->index(['user_id', 'source_key', 'subject_type', 'subject_id'], 'next_action_snoozes_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_action_snoozes');
    }
};
