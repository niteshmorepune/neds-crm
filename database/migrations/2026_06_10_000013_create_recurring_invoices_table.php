<?php

use App\Enums\RecurringFrequency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency')->default(RecurringFrequency::Monthly->value);
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_on');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('discount')->default(0);
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_on']);
        });

        Schema::create('recurring_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('sac_code')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('rate')->default(0);
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_items');
        Schema::dropIfExists('recurring_invoices');
    }
};
