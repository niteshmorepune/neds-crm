<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            // Reuses App\Enums\DeliverableStatus (Pending/Submitted/Received)
            // — same enum ProjectDeliverable and Project.requirement_status
            // already use, so this is the third reuse rather than a fourth
            // near-identical enum.
            $table->string('status')->default('pending');
            $table->date('requested_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('received_date')->nullable();
            // Who's chasing THIS requirement — distinct from
            // service_assignments.user_id (who generally works on the
            // service as a whole); the two can differ.
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            // The received file, once uploaded, lives in client_assets (not a
            // second, separate attachment) so it shows up in both places.
            $table->foreignId('client_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_requirements');
    }
};
