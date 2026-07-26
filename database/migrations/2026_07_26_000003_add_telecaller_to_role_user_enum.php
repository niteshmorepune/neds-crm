<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * role_user (additional roles pivot, created 2026-07-08 — after the Intern
 * role shipped) has its own enum('role', ...) column, so it needs the same
 * treatment as users.role and menu_item_role.role. Intern never needed this
 * third migration only because role_user didn't exist yet when it was added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", UserRole::values());
            DB::statement("ALTER TABLE `role_user` MODIFY COLUMN `role` ENUM('{$values}') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", array_filter(UserRole::values(), fn ($v) => $v !== 'telecaller'));
            DB::statement("ALTER TABLE `role_user` MODIFY COLUMN `role` ENUM('{$values}') NOT NULL");
        }
    }
};
