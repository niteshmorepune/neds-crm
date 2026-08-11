<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // paise
            $table->unsignedBigInteger('amount_applied')->default(0); // paise
            $table->date('received_on');
            $table->string('mode'); // PaymentMode
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('outstanding'); // ClientAdvanceStatus
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_advances');
    }
};
