<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retrofits real role-based visibility onto Important Links — previously
     * `department` was a display label only, visible to every logged-in
     * user regardless of value. No rows here for a given link = visible to
     * everyone, same non-breaking default every existing link keeps.
     */
    public function up(): void
    {
        Schema::create('important_link_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('important_link_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['important_link_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('important_link_role');
    }
};
