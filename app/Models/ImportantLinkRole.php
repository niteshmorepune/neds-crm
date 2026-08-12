<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row restricting an ImportantLink to a role. Mirrors MenuItemRole.
 */
class ImportantLinkRole extends Model
{
    protected $table = 'important_link_role';

    protected $fillable = [
        'important_link_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
        ];
    }

    public function importantLink(): BelongsTo
    {
        return $this->belongsTo(ImportantLink::class);
    }
}
