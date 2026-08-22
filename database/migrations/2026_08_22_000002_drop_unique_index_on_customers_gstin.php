<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same class of bug as the invoice_number fix (see the migration right
     * before this one): a DB-level unique index can't tell a soft-deleted
     * customer's gstin apart from a live one, so a deleted customer would
     * permanently block their real GSTIN from ever being reused on a
     * re-onboarded client. CustomerStoreRequest/CustomerUpdateRequest
     * already exclude trashed rows via ->withoutTrashed(), so that's now
     * the sole place uniqueness (among live customers) is enforced.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['gstin']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('gstin');
        });
    }
};
