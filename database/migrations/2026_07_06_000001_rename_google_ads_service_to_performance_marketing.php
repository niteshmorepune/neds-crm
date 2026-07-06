<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the "Google Ads" service in place (same row, same id — every
 * deal/lead/project/ticket/quotation/recurring-invoice keyed by service_id
 * keeps working) to "Performance Marketing", which better matches the
 * broader paid-media scope of work (audience research, conversion tracking,
 * creative testing — not just Google Ads specifically).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')
            ->where('slug', 'google-ads')
            ->update(['name' => 'Performance Marketing', 'slug' => 'performance-marketing']);
    }

    public function down(): void
    {
        DB::table('services')
            ->where('slug', 'performance-marketing')
            ->update(['name' => 'Google Ads', 'slug' => 'google-ads']);
    }
};
