<?php

namespace App\Models;

use App\Enums\WorkFromHomeRequestStatus;
use App\Enums\WorkFromHomeRequestType;
use App\Models\Concerns\LogsActivity;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to work remotely for a date range — distinct from LeaveRequest
 * because the person IS working on an approved WFH day, just not from the
 * office. Deliberately never touches Attendance on approval (confirmed with
 * the owner): the person still self-check-in/out exactly as on any other
 * day, so late/absent stays visible; WorkFromHomeRequestController only
 * surfaces a "Remote" badge on the Attendance page for a date an approved
 * request covers (see AttendanceController::index()'s $remoteDates).
 */
class WorkFromHomeRequest extends Model
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
            'status' => WorkFromHomeRequestStatus::class,
            'type' => WorkFromHomeRequestType::class,
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
        return $query->where('status', WorkFromHomeRequestStatus::Pending);
    }

    /**
     * The dates in this request's range that are actual office days
     * (Mon-Sat) — same convention as LeaveRequest::businessDays().
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

    public function dayCount(): float
    {
        return $this->type === WorkFromHomeRequestType::HalfDay ? 0.5 : (float) count($this->businessDays());
    }
}
