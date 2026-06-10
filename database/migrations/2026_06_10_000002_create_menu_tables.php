<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // stable identifier, e.g. "menu-controller"
            $table->string('label');                  // sidebar text, e.g. "Clients"
            $table->string('icon')->nullable();       // icon name for the sidebar
            $table->string('route');                  // named route this item links to
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        // Role defaults. This pivot is the source of truth for ROUTE ACCESS
        // (admin bypasses it). It also seeds default sidebar visibility.
        Schema::create('menu_item_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->enum('role', UserRole::values());
            $table->timestamps();

            $table->unique(['menu_item_id', 'role']);
        });

        // Per-user overrides. COSMETIC ONLY: they show/hide a sidebar item for a
        // specific user. They do NOT grant or remove route access — that stays
        // role-based and is enforced by middleware/Policies.
        Schema::create('menu_item_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('access', ['granted', 'revoked']);
            $table->timestamps();

            $table->unique(['menu_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_user');
        Schema::dropIfExists('menu_item_role');
        Schema::dropIfExists('menu_items');
    }
};
