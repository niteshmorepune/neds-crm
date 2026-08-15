<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visibility_audit_funnel_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('tier')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visibility_audit_funnel_events');
    }
};
