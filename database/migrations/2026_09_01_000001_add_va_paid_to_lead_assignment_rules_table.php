<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A third, mutually-exclusive match type alongside utm_campaign/
        // service_id (enforced in LeadAssignmentRuleRequest, not the schema,
        // same as the other two) — a rule with va_paid=true routes a lead the
        // moment it pays for the Visibility Audit offer, if it's still
        // unowned at that point. See RecordVisibilityAuditPurchase.
        Schema::table('lead_assignment_rules', function (Blueprint $table) {
            $table->boolean('va_paid')->default(false)->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignment_rules', function (Blueprint $table) {
            $table->dropColumn('va_paid');
        });
    }
};
