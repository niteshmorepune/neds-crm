<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'vendor', 'cost', 'billing_cycle', 'renewal_date',
        'reminder_days_before', 'notes', 'is_active', 'reminder_sent_for',
    ];

    protected function casts(): array
    {
        return [
            'billing_cycle' => RecurringFrequency::class,
            'renewal_date' => 'date',
            'reminder_sent_for' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Once renewal_date has actually passed, the subscription auto-renewed —
     * roll it forward (possibly several cycles, if the reminder command
     * didn't run for a while) so renewal_date always reflects the next
     * upcoming charge, and clear the sent-for guard so that new cycle can be
     * reminded on its own schedule. No-op if renewal_date is still upcoming.
     */
    public function rollToNextCycleIfPast(): void
    {
        if ($this->renewal_date->copy()->startOfDay()->gte(now()->startOfDay())) {
            return;
        }

        $next = $this->renewal_date;
        while ($next->copy()->startOfDay()->lt(now()->startOfDay())) {
            $next = $this->billing_cycle->advance($next);
        }

        $this->update(['renewal_date' => $next, 'reminder_sent_for' => null]);
    }

    /**
     * Due for a reminder today: active, renewal_date within this row's own
     * reminder_days_before window, and not already reminded for this exact
     * renewal_date (same duplicate-guard shape as RecurringInvoice's
     * renewal_reminder_sent_for). Assumes rollToNextCycleIfPast() has already
     * run, so renewal_date is never in the past here.
     */
    public function isDueForReminder(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->reminder_sent_for?->equalTo($this->renewal_date)) {
            return false;
        }

        $daysUntil = now()->startOfDay()->diffInDays($this->renewal_date, false);

        return $daysUntil >= 0 && $daysUntil <= $this->reminder_days_before;
    }
}
