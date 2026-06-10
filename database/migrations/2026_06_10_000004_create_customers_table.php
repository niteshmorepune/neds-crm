<?php

use App\Enums\CustomerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('gstin', 15)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();

            // Billing address. state_code (GST state code) is captured now so
            // intra/inter-state GST in M3 needs no schema change.
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('state_code', 2)->nullable();
            $table->string('pincode', 10)->nullable();

            $table->json('tags')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default(CustomerStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
