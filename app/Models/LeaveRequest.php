<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
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
        'user_id', 'type', 'start_date', 'end_date', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
            'status' => LeaveRequestStatus::class,
            'type' => LeaveRequestType::class,
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

    /**
     * Number of leave days this request accounts for — a Half Day request
     * is always a single day counted as 0.5, regardless of business-day count.
     */
    public function dayCount(): float
    {
        return $this->type === LeaveRequestType::HalfDay ? 0.5 : (float) count($this->businessDays());
    }
}
