<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // Nullable, not cascaded: a user is deactivated in this app, never
            // hard-deleted in normal operation — nullOnDelete just means the
            // (customer, service) pairing survives the rare hard-delete case
            // as "assigned to nobody" rather than vanishing entirely.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One responsible employee per client x service — assigning a
            // different person updates this same row (updateOrCreate), never
            // inserts a second one.
            $table->unique(['customer_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_assignments');
    }
};
