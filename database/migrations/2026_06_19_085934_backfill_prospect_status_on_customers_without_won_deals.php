<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Customers created via lead conversion that have no won deal are Prospects,
     * not yet active clients. Backfill existing rows to match the new semantic.
     */
    public function up(): void
    {
        // IDs that were created from a lead conversion.
        $convertedIds = DB::table('leads')
            ->whereNotNull('converted_customer_id')
            ->pluck('converted_customer_id');

        if ($convertedIds->isEmpty()) {
            return;
        }

        // Among those, the ones that already have a won deal stay Active.
        $wonCustomerIds = DB::table('deals')
            ->whereIn('customer_id', $convertedIds)
            ->where('stage', 'won')
            ->whereNull('deleted_at')
            ->pluck('customer_id');

        $prospectIds = $convertedIds->diff($wonCustomerIds)->values();

        if ($prospectIds->isEmpty()) {
            return;
        }

        DB::table('customers')
            ->whereIn('id', $prospectIds)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->update(['status' => 'prospect']);
    }

    public function down(): void
    {
        // Restore prospects that came from lead conversion back to active.
        $convertedIds = DB::table('leads')
            ->whereNotNull('converted_customer_id')
            ->pluck('converted_customer_id');

        if ($convertedIds->isEmpty()) {
            return;
        }

        DB::table('customers')
            ->whereIn('id', $convertedIds)
            ->whereNull('deleted_at')
            ->where('status', 'prospect')
            ->update(['status' => 'active']);
    }
};
