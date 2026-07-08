<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An additional role a user holds beyond their primary `users.role`. See
 * User::hasRole() for how primary + additional roles combine.
 */
class UserRoleAssignment extends Model
{
    protected $table = 'role_user';

    protected $fillable = ['user_id', 'role'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
