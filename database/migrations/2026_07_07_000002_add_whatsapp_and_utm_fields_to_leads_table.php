<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B of the AI lead funnel: multi-channel capture.
 *
 * whatsapp_conversation_id lets an inbound WhatsApp message from an unmatched
 * phone number create (and then dedupe against) a Lead, mirroring how
 * tickets.whatsapp_conversation_id already dedupes ticket creation.
 *
 * utm_source/medium/campaign let the website lead-capture form (and future
 * channels) tag which campaign a lead came from, for later attribution
 * reporting (Phase D).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('whatsapp_conversation_id')->nullable()->unique()->after('next_follow_up_at');
            $table->string('utm_source')->nullable()->after('whatsapp_conversation_id');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_conversation_id', 'utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
