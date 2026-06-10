<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment milestones for a quotation (e.g. 40% advance / 40% UAT /
        // 20% go-live). Each becomes its own invoice when billed; all linked
        // to the same deal for a billed/collected/remaining view.
        Schema::create('quotation_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('percentage', 5, 2);
            $table->unsignedBigInteger('amount')->default(0); // paise, derived from subtotal
            $table->date('due_date')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_milestones');
    }
};
