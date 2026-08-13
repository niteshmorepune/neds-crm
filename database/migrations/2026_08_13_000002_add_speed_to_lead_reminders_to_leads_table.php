<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard columns for App\Console\Commands\EscalateUntouchedLeads — a
        // brand-new lead nobody has engaged with yet gets its owner reminded,
        // then Admin/Manager escalated to, if it's still untouched later.
        // Nullable timestamps rather than a boolean so "when" is visible too.
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('owner_reminder_sent_at')->nullable()->after('next_follow_up_at');
            $table->timestamp('manager_escalated_at')->nullable()->after('owner_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['owner_reminder_sent_at', 'manager_escalated_at']);
        });
    }
};
