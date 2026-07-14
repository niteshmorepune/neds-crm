<?php

use App\Enums\BiometricSyncStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_sync_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default(BiometricSyncStatus::Pending->value);
            $table->string('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_sync_requests');
    }
};
