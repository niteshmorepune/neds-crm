<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->integer('cost')->default(0);
            $table->string('billing_cycle');
            $table->date('renewal_date');
            $table->unsignedSmallInteger('reminder_days_before')->default(7);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('reminder_sent_for')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
