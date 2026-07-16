<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log of deal stage moves, used to compute stage-to-stage
 * conversion %. Starts empty on deploy — only deals that move stage AFTER
 * this ships get a trail (see CLAUDE.md decisions log / DealsBoard::
 * stageConversion() for why: reconstructing history for pre-existing deals
 * isn't reliable, so it's accepted as accumulating from here forward).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable(); // null = the deal's initial stage on creation
            $table->string('to_stage');
            $table->timestamps();
            $table->index(['deal_id', 'created_at']);
            $table->index('to_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_stage_transitions');
    }
};
