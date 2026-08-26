<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency columns for the "audit in progress" nudge (step 2 of the
 * post-payment conversion pipeline) — two separate columns, one per
 * channel, same convention as the first-invite/recovery-nudge email
 * columns (2026_08_26_120000): a lead whose WhatsApp send fails
 * independently of its email (or vice versa) can be retried on its own
 * channel without affecting the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->timestamp('in_progress_notified_at')->nullable()->after('lead_id');
            $table->timestamp('in_progress_notified_email_at')->nullable()->after('in_progress_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->dropColumn(['in_progress_notified_at', 'in_progress_notified_email_at']);
        });
    }
};
