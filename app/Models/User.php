<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'device_user_id',
        'google_meet_scheduling_link',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** AI-generated fields are set via forceFill/saveQuietly — never worth logging. */
    protected array $activityExcept = ['ai_daily_digest', 'ai_daily_digest_date'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'ai_daily_digest_date' => 'date',
        ];
    }

    /** Two-factor is fully set up (secret confirmed with a valid code). */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /** Roles for which two-factor is mandatory. */
    public function requiresTwoFactor(): bool
    {
        return $this->hasRole(UserRole::Admin, UserRole::Manager);
    }

    /**
     * Per-user menu visibility overrides (granted / revoked). Cosmetic only.
     */
    public function menuOverrides(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class)
            ->withPivot('access')
            ->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'owner_id');
    }

    /**
     * Additional roles held beyond the primary `role` column. The primary
     * role still drives sidebar caching (MenuResolver), the dashboard panel
     * (DashboardController), and 2FA enforcement — additional roles only
     * expand permission checks (hasRole/isAdmin) and role-targeted
     * notifications/dropdowns (see scopeWithAnyRole).
     */
    public function additionalRoles(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    /**
     * @param  UserRole|string  ...$roles
     */
    public function hasRole(...$roles): bool
    {
        $wanted = array_map(
            fn ($role) => $role instanceof UserRole ? $role : UserRole::from($role),
            $roles,
        );

        if (in_array($this->role, $wanted, true)) {
            return true;
        }

        return $this->additionalRoles->pluck('role')->contains(fn (UserRole $role) => in_array($role, $wanted, true));
    }

    /**
     * Primary + additional roles, deduped, for display (e.g. "Sales + Support").
     *
     * @return Collection<int, UserRole>
     */
    public function allRoles(): Collection
    {
        return collect([$this->role])
            ->merge($this->additionalRoles->pluck('role'))
            ->unique();
    }

    /**
     * Users holding any of the given roles, whether as their primary role or
     * an additional one. Use for role-targeted notification/eligibility
     * queries (e.g. `User::where('is_active', true)->withAnyRole(Admin, Manager)`)
     * — not for auto-assignment/routing, which stays primary-role-only by
     * design (see the multi-role-support decisions log entry in CLAUDE.md).
     */
    public function scopeWithAnyRole(Builder $query, UserRole|string ...$roles): Builder
    {
        $values = array_map(fn ($role) => $role instanceof UserRole ? $role->value : $role, $roles);

        return $query->where(fn (Builder $q) => $q
            ->whereIn('role', $values)
            ->orWhereHas('additionalRoles', fn (Builder $a) => $a->whereIn('role', $values)));
    }
}
