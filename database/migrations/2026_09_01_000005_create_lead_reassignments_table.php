<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A structured log alongside ReassignLead::handle()'s existing Note
        // (which stays as the visible timeline entry) -- the Note's reason is
        // free text embedded in a sentence, not reliably aggregable. Written
        // by the one shared action, so it covers both the ad-hoc "Reassign"
        // button and the deactivation bulk-handover for free. Immutable log,
        // no updated_at.
        Schema::create('lead_reassignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reassigned_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_reassignments');
    }
};
