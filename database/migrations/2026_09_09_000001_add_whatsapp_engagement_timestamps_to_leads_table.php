<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // welcome_message_sent_at: idempotency guard for
        // SendLeadWelcomeMessageJob, the automatic "thanks for reaching
        // out, what's a good time to call?" WhatsApp send fired the moment
        // a Meta Ads lead is created -- the whole point is opening the
        // 24-hour WhatsApp session window on a lead who's never messaged
        // us, so this must fire at most once per lead.
        // last_checkin_sent_at: purely informational + a soft cooldown for
        // the manual "Send WhatsApp check-in" button (LeadController::
        // sendCheckIn()) -- staff-triggered, re-sendable, but the
        // controller checks this to avoid an accidental repeat send within
        // 24h to the same lead.
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('welcome_message_sent_at')->nullable()->after('visibility_audit_invited_at');
            $table->timestamp('last_checkin_sent_at')->nullable()->after('welcome_message_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['welcome_message_sent_at', 'last_checkin_sent_at']);
        });
    }
};
