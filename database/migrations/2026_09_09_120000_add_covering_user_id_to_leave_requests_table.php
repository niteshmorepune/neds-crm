<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Set at approval time, required only when the leave-taker holds
            // Sales or Telecaller and has open leads at that moment (see
            // App\Services\LeaveCoverage). Drives which teammate gets
            // temporarily added as a wadesk.in conversation assignee for
            // those leads' chats while the leave is active.
            $table->foreignId('covering_user_id')->nullable()->after('reviewed_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('covering_user_id');
        });
    }
};
