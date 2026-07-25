<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAccountConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'google_email',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True once the access token is expired (or about to, within a minute) and needs refreshing. */
    public function isExpired(): bool
    {
        return $this->expires_at->subMinute()->isPast();
    }

    /**
     * The single company-wide connection used to create Meet links and read
     * recordings/transcripts on behalf of any staff member — since 2026-07-25
     * this is Admin-only (see GoogleConnectionController), but the column
     * itself is still keyed per-user, so this resolves to whichever Admin
     * most recently connected (handles a second admin reconnecting to "take
     * over" the connection without needing a schema change).
     */
    public static function forCompany(): ?self
    {
        return static::whereHas('user', fn ($q) => $q->where('role', UserRole::Admin->value))
            ->latest('connected_at')
            ->first();
    }
}
