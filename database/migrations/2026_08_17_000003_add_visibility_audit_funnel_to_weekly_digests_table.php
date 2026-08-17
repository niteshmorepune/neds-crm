<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_digests', function (Blueprint $table) {
            $table->unsignedInteger('visibility_audit_eligible_count')->default(0)->after('client_radar_overdue_invoice_count');
            $table->unsignedInteger('visibility_audit_invited_count')->default(0)->after('visibility_audit_eligible_count');
            $table->unsignedInteger('visibility_audit_landing_viewed_count')->default(0)->after('visibility_audit_invited_count');
            $table->unsignedInteger('visibility_audit_checkout_viewed_count')->default(0)->after('visibility_audit_landing_viewed_count');
            $table->unsignedInteger('visibility_audit_paid_count')->default(0)->after('visibility_audit_checkout_viewed_count');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_digests', function (Blueprint $table) {
            $table->dropColumn([
                'visibility_audit_eligible_count',
                'visibility_audit_invited_count',
                'visibility_audit_landing_viewed_count',
                'visibility_audit_checkout_viewed_count',
                'visibility_audit_paid_count',
            ]);
        });
    }
};
