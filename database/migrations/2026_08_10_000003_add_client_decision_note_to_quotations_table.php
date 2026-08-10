<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Panel Tier 0: clients can now Accept/Reject a quotation directly
 * in the portal. An optional note on rejection gives staff the "why"
 * without needing a phone call — mirrors the approval_notes column added
 * for the internal review flow in the previous migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->text('client_decision_note')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('client_decision_note');
        });
    }
};
