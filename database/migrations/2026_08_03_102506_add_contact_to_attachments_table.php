<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An attachment is uploaded by either an internal user (uploaded_by)
        // or a portal contact (contact_id) — e.g. a client attaching a file
        // when raising a ticket. Mirrors ticket_replies' user_id/contact_id split.
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('uploaded_by')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
