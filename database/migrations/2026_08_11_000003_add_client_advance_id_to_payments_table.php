<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Set only when this payment was settled by applying a
            // previously-recorded ClientAdvance rather than fresh cash today.
            $table->foreignId('client_advance_id')->nullable()->after('gateway_payment_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_advance_id');
        });
    }
};
