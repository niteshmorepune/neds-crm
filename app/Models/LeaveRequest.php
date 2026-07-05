<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Models\Concerns\LogsActivity;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'start_date', 'end_date', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
            'status' => LeaveRequestStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Pending);
    }

    /**
     * The dates in this request's range that are actual office days
     * (Mon-Sat) — Sunday is never a working day, so it's excluded.
     *
     * @return list<string>
     */
    public function businessDays(): array
    {
        return collect(CarbonPeriod::create($this->start_date, $this->end_date))
            ->reject(fn ($date) => $date->isSunday())
            ->map(fn ($date) => $date->toDateString())
            ->values()
            ->all();
    }
}
