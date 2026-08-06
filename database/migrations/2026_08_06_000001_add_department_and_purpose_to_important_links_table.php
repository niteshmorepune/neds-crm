<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('important_links', function (Blueprint $table) {
            // Both nullable and left untouched on existing rows — an
            // existing link lands in an "Uncategorized" bucket in the UI
            // rather than being guessed at. Bounded to LinkDepartment/
            // LinkPurpose (plain string column, enum cast on the model),
            // not free text, matching this app's bounded-registry
            // convention for taxonomy-like fields.
            $table->string('department')->nullable()->after('customer_id');
            $table->string('purpose')->nullable()->after('department');

            $table->index('department');
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('important_links', function (Blueprint $table) {
            $table->dropIndex(['department']);
            $table->dropIndex(['purpose']);
            $table->dropColumn(['department', 'purpose']);
        });
    }
};
