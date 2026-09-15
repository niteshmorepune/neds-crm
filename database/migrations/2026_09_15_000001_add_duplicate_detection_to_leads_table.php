<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('possible_duplicate_of_lead_id')->nullable()->after('converted_deal_id')
                ->constrained('leads')->nullOnDelete();
            $table->timestamp('duplicate_flagged_at')->nullable()->after('possible_duplicate_of_lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('possible_duplicate_of_lead_id');
            $table->dropColumn('duplicate_flagged_at');
        });
    }
};
