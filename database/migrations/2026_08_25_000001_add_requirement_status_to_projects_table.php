<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Reuses App\Enums\DeliverableStatus (Pending/Submitted/Received) —
            // the requirements doc asks for exactly these three values for a
            // single per-project "Client Requirement Status" field, and
            // ProjectDeliverable already uses this same enum for its own
            // per-item status, so a second identical enum would only exist to
            // give it a different name.
            $table->string('requirement_status')->default('pending')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('requirement_status');
        });
    }
};
