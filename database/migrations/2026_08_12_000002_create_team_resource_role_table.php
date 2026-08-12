<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_resource_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_resource_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['team_resource_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_resource_role');
    }
};
