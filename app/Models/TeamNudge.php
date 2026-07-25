<?php

namespace App\Models;

use App\Enums\NudgeAutoDetectType;
use App\Enums\NudgeRecurrence;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TeamNudge extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title', 'description', 'target_role', 'recurrence',
        'auto_detect_type', 'due_date', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_role' => UserRole::class,
            'recurrence' => NudgeRecurrence::class,
            'auto_detect_type' => NudgeAutoDetectType::class,
            'due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(TeamNudgeStatus::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Monday of the current week (Asia/Kolkata), date-only — the single
     * source of truth for "which weekly period is this" shared by the
     * rollover/auto-detect commands and the dashboard widget, so a status
     * row created lazily by the widget always lines up with one created by
     * the scheduled job.
     */
    public static function currentPeriodStart(): Carbon
    {
        return now('Asia/Kolkata')->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * Nudges targeting this user — null target_role means everyone, otherwise
     * matched via hasRole() so an ADDITIONAL role also sees it (same rule as
     * Menu Controller sidebar access, not the primary-role-only dashboard
     * panel — a nudge is an access question, not a "which single panel"
     * question).
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('target_role');
            foreach ($user->allRoles() as $role) {
                $q->orWhere('target_role', $role->value);
            }
        });
    }
}
