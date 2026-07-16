<?php

namespace App\Models;

use App\Enums\TargetPeriodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SalesTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_type',
        'period_start',
        'target_value',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
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
     * @param  int|null  $userId  null = the company-wide target
     */
    public function scopeForPeriod(Builder $query, ?int $userId, TargetPeriodType $type, Carbon $periodStart): Builder
    {
        return $query->where('user_id', $userId)
            ->where('period_type', $type->value)
            ->whereDate('period_start', $periodStart);
    }
}
