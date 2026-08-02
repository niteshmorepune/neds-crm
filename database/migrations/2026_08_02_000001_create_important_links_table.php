<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('important_links', function (Blueprint $table) {
            $table->id();
            // Null = a company-wide link (Admin/Manager managed); set = a
            // per-client link, visible to any staff member viewing that
            // client's page. No cascade on delete — mirrors how every other
            // Customer-owned record (Deal, Ticket, Invoice…) survives a soft
            // delete and is labeled "Client removed" rather than vanishing.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('label');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('important_links');
    }
};
