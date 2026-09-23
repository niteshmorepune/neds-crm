<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignmentSetting extends Model
{
    protected $fillable = ['enabled', 'forced_user_id', 'updated_by'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function forcedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forced_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The single settings row. Defaults to disabled the first time it's read. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['enabled' => false]);
    }

    /**
     * The forced target, but only if still an eligible active Sales rep —
     * same "re-check at match time" guard as LeadAssignmentRule::
     * eligibleAssignee(), so a switch left on against a since-deactivated
     * or role-changed user falls through to the normal rule/round-robin
     * path instead of silently assigning to someone ineligible.
     */
    public function eligibleForcedUser(): ?User
    {
        if (! $this->enabled) {
            return null;
        }

        $user = $this->forcedUser;

        return $user && $user->is_active && $user->role === UserRole::Sales ? $user : null;
    }
}
