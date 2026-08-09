<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A personal, dashboard-set reminder to follow up with a client — a phone
 * call, sharing a report, sending a quotation, chasing a deal, etc. Deliberately
 * decoupled from CallLog's own follow_up_at/next_action pair (see
 * SendCallFollowUpReminders): that one only exists once an actual call has
 * been logged, this one is meant to be set ahead of any action taking place.
 */
class FollowUpReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'remind_at',
        'next_action',
        'notified_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'notified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isDue(): bool
    {
        return $this->completed_at === null && $this->remind_at->isPast();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Due now (or overdue), not yet notified — used by the scheduled command.
     */
    public function scopeAwaitingNotification(Builder $query): Builder
    {
        return $query->pending()
            ->whereNull('notified_at')
            ->where('remind_at', '<=', now());
    }
}
