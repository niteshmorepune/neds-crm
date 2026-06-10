<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A reply is authored by either an internal user (user_id) or a portal
        // contact (contact_id).
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
