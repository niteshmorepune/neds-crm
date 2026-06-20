<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->date('last_reminder_sent_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->dropColumn('last_reminder_sent_at');
        });
    }
};
