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
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('{$values}') NOT NULL DEFAULT 'sales'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $values = implode("','", array_filter(UserRole::values(), fn ($v) => $v !== 'telecaller'));
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('{$values}') NOT NULL DEFAULT 'sales'");
        }
    }
};
