<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escalation Management (Manager panel doc "clarify first" item, scoped via
 * AskUserQuestion to: manual escalate button + persisted state + a
 * manager-facing filterable list — not automatic SLA-breach reassignment,
 * which already has its own path via CheckTicketSla). escalated_at is the
 * single source of truth for "is this ticket currently escalated" (mirrors
 * resolved_at's nullable-timestamp-as-state pattern already used on this
 * same table); escalated_by records who raised it for the notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('resolved_at');
            $table->foreignId('escalated_by')->nullable()->after('escalated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropColumn('escalated_at');
        });
    }
};
