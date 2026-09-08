<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same field, same reasoning as leads.stall_reason (same migration
        // batch) -- a deal can keep stalling in Negotiation for the same
        // real reasons after conversion, per the VA Funnel data (2/9 paid
        // customers reached Negotiation and stalled there too).
        Schema::table('deals', function (Blueprint $table) {
            $table->string('stall_reason')->nullable()->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('stall_reason');
        });
    }
};
