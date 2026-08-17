<?php

namespace App\Models;

use App\Enums\TargetMetric;
use App\Enums\TargetPeriodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Admin/manager-set KRA targets for the non-Sales roles — Support, Accounts,
 * Intern, Telecaller — one metric per role (App\Enums\TargetMetric).
 * Deliberately a separate table from SalesTarget (see the migration's own
 * note) rather than widening it.
 */
class RoleTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metric',
        'period_type',
        'period_start',
        'target_value',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metric' => TargetMetric::class,
            'period_type' => TargetPeriodType::class,
            'period_start' => 'date',
            'target_value' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  int|null  $userId  null = the role-wide target
     */
    public function scopeForPeriod(Builder $query, ?int $userId, TargetMetric $metric, TargetPeriodType $type, Carbon $periodStart): Builder
    {
        return $query->where('user_id', $userId)
            ->where('metric', $metric->value)
            ->where('period_type', $type->value)
            ->whereDate('period_start', $periodStart);
    }
}
