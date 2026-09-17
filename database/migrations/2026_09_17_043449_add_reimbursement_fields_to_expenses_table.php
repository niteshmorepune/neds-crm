<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks when a team member is paid back for an expense they covered out of
 * their own pocket. `reimbursed_at` null = still owed; set = paid back on
 * that date. `reimbursed_by` records who confirmed it (Admin/Manager/
 * Accounts, same roles who can already edit an expense).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->date('reimbursed_at')->nullable()->after('notes');
            $table->foreignId('reimbursed_by')->nullable()->after('reimbursed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reimbursed_by');
            $table->dropColumn('reimbursed_at');
        });
    }
};
