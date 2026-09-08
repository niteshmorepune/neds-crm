<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Captures the Meta Ads lead form's "What is your biggest goal?"
        // question as a structured field (previously fell into the generic
        // "Additional form answers" note dump — see ImportMetaLead's
        // matchGoal()), settable on any lead regardless of source by a
        // telecaller/sales rep asking directly. website_url/gbp_url are the
        // link a telecaller asks for once the goal points at a concrete
        // next step (see LeadGoal::needsWebsiteOrGbp()).
        Schema::table('leads', function (Blueprint $table) {
            $table->string('goal')->nullable()->after('service_id');
            $table->string('website_url', 2048)->nullable()->after('goal');
            $table->string('gbp_url', 2048)->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['goal', 'website_url', 'gbp_url']);
        });
    }
};
