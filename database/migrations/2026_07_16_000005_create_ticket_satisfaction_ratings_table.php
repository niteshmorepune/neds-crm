<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lightweight CSAT pulse: one rating per resolved/closed ticket, submitted
 * once by the client via the portal. Feeds ClientRadarService as a new
 * "Low Satisfaction" signal rather than only inferring risk from behaviour
 * (no contact, overdue invoice) — see CLAUDE.md decisions log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_satisfaction_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_satisfaction_ratings');
    }
};
