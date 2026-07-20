<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title', 'body', 'audience', 'is_pinned', 'starts_at', 'ends_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'is_pinned' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Within its display window: already started, and not yet expired
     * (ends_at null means "no expiry" — a standing notice).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function scopeForStaff(Builder $query): Builder
    {
        return $query->whereIn('audience', [AnnouncementAudience::Staff->value, AnnouncementAudience::Both->value]);
    }

    public function scopeForClients(Builder $query): Builder
    {
        return $query->whereIn('audience', [AnnouncementAudience::Clients->value, AnnouncementAudience::Both->value]);
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('starts_at');
    }
}
