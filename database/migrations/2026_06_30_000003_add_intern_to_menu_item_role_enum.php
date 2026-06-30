<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", UserRole::values());
            DB::statement("ALTER TABLE `menu_item_role` MODIFY COLUMN `role` ENUM('{$values}') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", array_filter(UserRole::values(), fn ($v) => $v !== 'intern'));
            DB::statement("ALTER TABLE `menu_item_role` MODIFY COLUMN `role` ENUM('{$values}') NOT NULL");
        }
    }
};
