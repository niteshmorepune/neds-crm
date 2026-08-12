<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Display-only grouping for the sidebar (collapsible sections) — not
     * used in any authorization check, that stays on menu_item_role/
     * menu_item_user exactly as before. Nullable since it's purely cosmetic;
     * MenuItemsSeeder assigns every current item a group.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('group')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }
};
