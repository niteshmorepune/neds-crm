<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row restricting a TeamResource to a role. Mirrors MenuItemRole.
 */
class TeamResourceRole extends Model
{
    protected $table = 'team_resource_role';

    protected $fillable = [
        'team_resource_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    public function teamResource(): BelongsTo
    {
        return $this->belongsTo(TeamResource::class);
    }
}
