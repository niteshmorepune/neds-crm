<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // Bounded to TeamResourceCategory (plain string column, enum cast
            // on the model) — same taxonomy convention as important_links'
            // department/purpose columns.
            $table->string('category')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_resources');
    }
};
