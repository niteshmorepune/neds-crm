<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Captured the moment AiAssistant::suggestDealLostReason() computes a
        // suggestion (DealsBoard::suggestLostReason() / DealLostReasonField::
        // suggest()), independent of what the rep ultimately picks in
        // lost_reason -- lets a report compare "AI suggested" vs "rep chose"
        // without a second AI call. Null when no suggestion was made (thin
        // note/call history) or the deal never reached the Lost picker.
        Schema::table('deals', function (Blueprint $table) {
            $table->string('ai_suggested_lost_reason')->nullable()->after('lost_reason');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('ai_suggested_lost_reason');
        });
    }
};
