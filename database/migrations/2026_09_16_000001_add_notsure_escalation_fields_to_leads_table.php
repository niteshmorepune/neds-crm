<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Baseline + bookkeeping for App\Console\Commands\EscalateNotSureLeads.
        // notsure_at is the "since when" baseline the owner/manager hour
        // thresholds measure from -- stamped by LeadObserver whenever goal
        // transitions (or is set at creation) to NotSure, independent of
        // LeadWantsExpertAdviceNotification's own one-time immediate ping.
        // The other two are one-shot/cooldown guard columns, same
        // forceFill()->saveQuietly() bookkeeping pattern as
        // owner_reminder_sent_at/manager_escalated_at.
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('notsure_at')->nullable()->after('goal');
            $table->timestamp('notsure_owner_notified_at')->nullable()->after('notsure_at');
            $table->timestamp('notsure_manager_escalated_at')->nullable()->after('notsure_owner_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['notsure_at', 'notsure_owner_notified_at', 'notsure_manager_escalated_at']);
        });
    }
};
