<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 of the post-payment conversion pipeline: staff marks the audit
 * content as prepared/ready, which fires a reminder to the lead's owner to
 * schedule the 15-min Gmeet before ever sharing the report. Set manually
 * (App\Http\Controllers\VisibilityAuditPurchaseController::markReady()) —
 * unlike every other timestamp column in this pipeline, there is no way to
 * auto-detect "a human finished preparing a slide deck".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->timestamp('audit_ready_at')->nullable()->after('in_progress_notified_email_at');
            $table->foreignId('audit_ready_by')->nullable()->after('audit_ready_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audit_ready_by');
            $table->dropColumn('audit_ready_at');
        });
    }
};
