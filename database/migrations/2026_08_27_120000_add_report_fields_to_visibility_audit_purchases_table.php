<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4 of the post-payment conversion pipeline: one-click "Send Audit
 * Report" (email attachment + WhatsApp link), gated on the Gmeet actually
 * having happened (step 3). report_token is a permanent, unguessable
 * identifier for the public report-view link (App\Http\Controllers\
 * VisibilityAuditReportController) -- same shape as ContentPiece's own
 * upload_token, but deliberately non-expiring since this is a lasting
 * reference the client may revisit, not a one-time upload window.
 * report_sent_at/report_sent_by are updated on every send, including a
 * deliberate resend -- there is no idempotency guard here (unlike every
 * scheduled job elsewhere in this pipeline), since this is always a single
 * discrete staff click, not a repeated cron sweep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->string('report_token')->nullable()->unique()->after('audit_ready_by');
            $table->timestamp('report_sent_at')->nullable()->after('report_token');
            $table->foreignId('report_sent_by')->nullable()->after('report_sent_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_sent_by');
            $table->dropColumn(['report_token', 'report_sent_at']);
        });
    }
};
