<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * wadesk_message_id lets WadeskMessageStatusController find a
     * SendLeadWelcomeMessageJob/SendLeadCheckInJob send back later, the
     * same way VisibilityAuditTouch.meta.wadesk_message_id already does for
     * VA-funnel sends -- see that controller's docblock. Real incident,
     * 2026-09-10: a lead_welcome/lead_checkin send was rejected by Meta's
     * "healthy ecosystem engagement" pacing throttle (error 131049) and the
     * CRM had no way to learn about it, silently overstating the send as
     * successful forever.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('welcome_message_wadesk_id')->nullable()->after('welcome_message_sent_at');
            $table->string('checkin_wadesk_id')->nullable()->after('last_checkin_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['welcome_message_wadesk_id', 'checkin_wadesk_id']);
        });
    }
};
