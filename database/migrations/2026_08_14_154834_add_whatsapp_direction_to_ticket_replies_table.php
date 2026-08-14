<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            // 'inbound' | 'outbound' — set only for a reply that came from a
            // wadesk.in WhatsApp message with no CRM user/portal Contact
            // author (the actual customer, a wadesk.in agent, or the AI
            // after-hours assistant); null for every ordinary CRM-authored
            // reply. Drives TicketReply::isFromCustomer()/authorName().
            $table->string('whatsapp_direction')->nullable()->after('is_internal');

            // Display name when there's no user_id/contact_id to resolve one
            // from — the WhatsApp contact's name (inbound), the wadesk.in
            // agent's name (outbound, human), or "AI Assistant" (outbound, AI).
            $table->string('external_sender_name')->nullable()->after('whatsapp_direction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_direction', 'external_sender_name']);
        });
    }
};
