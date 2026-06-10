<?php

use App\Enums\CallOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('callable'); // customer or lead
            $table->string('direction');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('outcome')->default(CallOutcome::Connected->value);
            $table->text('notes')->nullable();
            $table->timestamp('called_at');
            $table->timestamps();

            $table->index(['user_id', 'called_at']);
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
