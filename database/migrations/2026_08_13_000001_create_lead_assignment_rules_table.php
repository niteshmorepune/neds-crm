<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin/manager-configured overrides checked by LeadObserver::autoAssign()
        // before its least-loaded round-robin fallback. Exactly one of
        // utm_campaign/service_id is set per row (enforced in
        // LeadAssignmentRuleRequest, not the schema) — a campaign-name rule
        // targets one specific ad, a service-id rule targets a whole service
        // line for leads with no more specific campaign rule. assigned_user_id
        // cascades on delete: a rule pointing at a hard-deleted user is dead
        // weight, not worth blocking the deletion over (users are normally
        // deactivated, not deleted, so this is a rare edge case).
        Schema::create('lead_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('utm_campaign')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assignment_rules');
    }
};
