<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedup key for wadesk.in's WhatsApp Calling API -> CallLog sync
     * (App\Http\Controllers\Api\WadeskCallLogController) -- mirrors the
     * same "external reference id column" pattern already used for
     * leads.welcome_message_wadesk_id/checkin_wadesk_id, so a retried
     * webhook delivery can never double-log the same call.
     */
    public function up(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->string('wadesk_call_id')->nullable()->unique()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn('wadesk_call_id');
        });
    }
};
