<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * @param  UserRole|string  ...$roles
     */
    public function hasRole(...$roles): bool
    {
        return in_array($this->role, array_map(
            fn ($role) => $role instanceof UserRole ? $role : UserRole::from($role),
            $roles,
        ), true);
    }
}
