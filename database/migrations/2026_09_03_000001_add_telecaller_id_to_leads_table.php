<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('telecaller_id')->nullable()->after('owner_id')
                ->constrained('users')->nullOnDelete();

            $table->index(['telecaller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['telecaller_id', 'status']);
            $table->dropConstrainedForeignId('telecaller_id');
        });
    }
};
