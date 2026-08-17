<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalizes `sales_targets`' shape to the KRA metric of each non-Sales
 * role (Support: tickets resolved, Accounts: collections recorded, Intern:
 * tasks completed, Telecaller: calls made) — a separate table rather than
 * widening `sales_targets` itself, since that table is load-bearing for
 * Incentives/Partner Commission and this needs no migration of that data.
 * `target_value` is a plain count for every metric except
 * collections_recorded, which is paise (App\Support\Money) like every other
 * money column in this app. `user_id` null = a role-wide target, same
 * "distinct NULLs, so uniqueness for the role-wide row is guaranteed by
 * always writing through the same keyed updateOrCreate call, not the DB
 * constraint" caveat as `sales_targets`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->string('period_type');
            $table->date('period_start');
            $table->integer('target_value');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'metric', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_targets');
    }
};
