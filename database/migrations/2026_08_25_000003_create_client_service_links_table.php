<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_service_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // Free-text label ("Website URL", "GBP Link", "Search Console"),
            // not a curated per-service-type field list — see the Client
            // Profile overhaul decisions log entry for why (a hardcoded
            // service-type -> field mapping is fragile against the kind of
            // service-taxonomy rename this app has already hit once).
            $table->string('label');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'service_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_service_links');
    }
};
