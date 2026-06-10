<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row mapping a menu item to a role that may access it by default.
 * Source of truth for role-based route access.
 */
class MenuItemRole extends Model
{
    protected $table = 'menu_item_role';

    protected $fillable = [
        'menu_item_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
