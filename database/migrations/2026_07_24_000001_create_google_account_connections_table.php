<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per staff user who has connected their own Google account
 * (per-user OAuth, confirmed with the owner — not domain-wide delegation)
 * so the CRM can read their Calendar events and Drive-hosted Meet
 * recordings/transcripts on their behalf. Tokens are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_account_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->string('scopes');
            $table->string('google_email')->nullable();
            $table->timestamp('connected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_account_connections');
    }
};
