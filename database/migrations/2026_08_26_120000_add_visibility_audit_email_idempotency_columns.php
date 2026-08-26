<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated idempotency columns for the new email-channel jobs
 * (SendVisibilityAuditFirstInviteEmailJob / SendVisibilityAuditRecoveryNudgeEmailJob)
 * — kept separate from visibility_audit_invited_at/nudged_at (which stay
 * WhatsApp-specific, unchanged) rather than reused, so a lead whose email
 * send fails independently of its WhatsApp send (or vice versa) can be
 * retried on its own channel without affecting the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('visibility_audit_invite_emailed_at')->nullable()->after('visibility_audit_invited_at');
        });

        Schema::table('visibility_audit_funnel_events', function (Blueprint $table) {
            $table->timestamp('nudged_email_at')->nullable()->after('nudged_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('visibility_audit_invite_emailed_at');
        });

        Schema::table('visibility_audit_funnel_events', function (Blueprint $table) {
            $table->dropColumn('nudged_email_at');
        });
    }
};
