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
        // Pure idempotency ledger — one row per wadesk.in Message.id we've
        // already processed, so a retried webhook delivery for the same
        // message never creates a second TicketReply/Note. Deliberately not
        // linked to Ticket/Lead/Note by foreign key: its only job is "have I
        // seen this wadesk.in message_id before," nothing else reads it.
        Schema::create('wadesk_message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('wadesk_message_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wadesk_message_logs');
    }
};
