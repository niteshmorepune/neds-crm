<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give-up-after-N-attempts guard for the "audit in progress" nudge (step 2
 * of the post-payment conversion pipeline). Real incident, 2026-08-27: the
 * visibility_audit_in_progress WhatsApp template was briefly unapproved,
 * and SendVisibilityAuditInProgressNudges (every 15 min, forever, since a
 * failed attempt never sets in_progress_notified_at) kept re-dispatching
 * the same ~8 purchases for over an hour before the template got approved
 * and it self-resolved. This time it was quick; a longer-lived
 * misconfiguration would have retried indefinitely.
 *
 * Two attempt counters + two gave-up timestamps, one pair per channel
 * (same "separate columns per channel so one failing independently of the
 * other" convention as in_progress_notified_at/in_progress_notified_email_at,
 * 2026_08_26_150000) -- a channel that's given up stops being dispatched
 * (see VisibilityAuditFunnelMetrics::pendingInProgressNudges()) without
 * affecting the other channel's own retry count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->unsignedTinyInteger('in_progress_whatsapp_attempts')->default(0)->after('in_progress_notified_at');
            $table->timestamp('in_progress_whatsapp_gave_up_at')->nullable()->after('in_progress_whatsapp_attempts');
            $table->unsignedTinyInteger('in_progress_email_attempts')->default(0)->after('in_progress_notified_email_at');
            $table->timestamp('in_progress_email_gave_up_at')->nullable()->after('in_progress_email_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->dropColumn([
                'in_progress_whatsapp_attempts',
                'in_progress_whatsapp_gave_up_at',
                'in_progress_email_attempts',
                'in_progress_email_gave_up_at',
            ]);
        });
    }
};
